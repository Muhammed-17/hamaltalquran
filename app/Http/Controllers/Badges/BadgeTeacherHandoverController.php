<?php

namespace App\Http\Controllers\Badges;

use App\Http\Controllers\Controller;
use App\Http\Requests\Badges\CancelBadgeOperationRequest;
use App\Http\Requests\Badges\StoreBadgeTeacherHandoverRequest;
use App\Models\Badges\BadgeTeacherHandover;
use App\Models\Badges\BadgeType;
use App\Models\User;
use App\Services\Badges\BadgeAccessService;
use App\Services\Badges\BadgeBalanceService;
use App\Services\Badges\BadgeTeacherHandoverService;
use Illuminate\Http\Request;

class BadgeTeacherHandoverController extends Controller
{
    /**
     * استعلام التسليمات داخل نطاق المستخدم ومصفّى بفلاتر الصفحة
     * (مستلم/نوع/حالة/شهر) — أساس مشترك للقائمة وملف التقرير.
     */
    private function handoverFilterQuery(Request $request, BadgeAccessService $access)
    {
        $user = $request->user();
        $visibleIds = $access->selectableRecipients($user)->pluck('id')->push($user->id);

        return BadgeTeacherHandover::query()
            ->when(!$user->hasRole(['admin', 'general_manager']), fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('teacher_id', $visibleIds)
                ->orWhere('source_user_id', $user->id)))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', (int) $request->teacher_id))
            ->when($request->filled('badge_type_id'), fn ($q) => $q->where('badge_type_id', (int) $request->badge_type_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('month') && preg_match('/^\d{4}-\d{2}$/', (string) $request->month), function ($q) use ($request) {
                [$year, $month] = explode('-', (string) $request->month);
                $q->whereYear('handover_date', (int) $year)->whereMonth('handover_date', (int) $month);
            });
    }

    /** زر التقرير: ملف Excel بالتسليمات حسب الفلاتر الحالية */
    /** صفحة تفاصيل «حسب المستلم»: المستلم لكل نوع لكل مستلم مع الملغي والعمليات */
    public function statisticsRecipients(Request $request, BadgeAccessService $access)
    {
        $this->authorize('viewAny', BadgeTeacherHandover::class);

        $base = $this->handoverFilterQuery($request, $access);
        $batchKey = fn ($h) => $h->batch_id ?? 'single-' . $h->id;

        $activeRows = (clone $base)->where('status', 'active')
            ->get(['id', 'teacher_id', 'badge_type_id', 'batch_id', 'quantity']);
        $cancelledByRecipient = (clone $base)->where('status', 'cancelled')
            ->selectRaw('teacher_id, SUM(quantity) as qty')
            ->groupBy('teacher_id')->pluck('qty', 'teacher_id');

        $matrix = $activeRows->groupBy('teacher_id')->map(fn ($rows, $teacherId) => [
            'ops' => $rows->map($batchKey)->unique()->count(),
            'total' => (int) $rows->sum('quantity'),
            'types' => $rows->groupBy('badge_type_id')->map(fn ($tRows) => (int) $tRows->sum('quantity')),
            'cancelled' => (int) ($cancelledByRecipient[$teacherId] ?? 0),
        ])->sortByDesc('total');

        $users = User::whereIn('id', $matrix->keys())->get(['id', 'name'])->keyBy('id');
        if ($request->filled('q')) {
            $q = (string) $request->q;
            $matrix = $matrix->filter(fn ($row, $teacherId) => isset($users[$teacherId]) && mb_stripos($users[$teacherId]->name, $q) !== false);
        }

        $usedTypeIds = $matrix->flatMap(fn ($row) => $row['types']->keys())->unique()->all();

        // قائمة المستلمين للفلتر: من لهم تسليمات داخل النطاق المرئي (دون فلتر المستلم نفسه)
        $requestNoTeacher = clone $request;
        $requestNoTeacher->query->remove('teacher_id');
        $requestNoTeacher->request->remove('teacher_id');
        $recipients = User::whereIn('id',
            $this->handoverFilterQuery($requestNoTeacher, $access)->distinct()->pluck('teacher_id')
        )->orderBy('name')->get(['id', 'name']);

        return view('badges.handovers.statistics-recipients', [
            'matrix' => $matrix,
            'users' => $users,
            'types' => BadgeType::whereIn('id', $usedTypeIds)->orderBy('name')->get(),
            'filterTypes' => BadgeType::orderBy('name')->get(),
            'recipients' => $recipients,
        ]);
    }

    public function report(Request $request, BadgeAccessService $access)
    {
        $base = fn () => $this->handoverFilterQuery($request, $access);

        $month = $request->filled('month') ? (string) $request->month : 'all';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BadgeHandoversReportExport($base),
            "handovers-report-{$month}.xlsx"
        );
    }

    /** صفحة إحصائيات التسليمات — نفس فلاتر القائمة، والأرقام من نفس النطاق المرئي */
    public function statistics(Request $request, BadgeAccessService $access)
    {
        $this->authorize('viewAny', BadgeTeacherHandover::class);

        $user = $request->user();
        $base = $this->handoverFilterQuery($request, $access);

        // مجموعا الحالات يُحسبان من نفس الفلاتر لكن دون فلتر الحالة نفسه
        $requestNoStatus = clone $request;
        $requestNoStatus->query->remove('status');
        $requestNoStatus->request->remove('status');
        $statusSums = $this->handoverFilterQuery($requestNoStatus, $access)
            ->selectRaw('status, COUNT(*) as records, SUM(quantity) as qty')
            ->groupBy('status')->get()->keyBy('status');

        // صفوف النشط: لبطاقات الإجمالي وعدّ العمليات (حسب batch_id) والجداول
        $activeRows = (clone $base)->where('status', 'active')
            ->get(['id', 'teacher_id', 'badge_type_id', 'batch_id', 'quantity']);
        $batchKey = fn ($h) => $h->batch_id ?? 'single-' . $h->id;

        $perType = $activeRows->groupBy('badge_type_id')->map(fn ($rows) => [
            'ops' => $rows->map($batchKey)->unique()->count(),
            'qty' => (int) $rows->sum('quantity'),
        ]);
        $perTypeCancelled = (clone $base)->where('status', 'cancelled')
            ->selectRaw('badge_type_id, SUM(quantity) as qty')
            ->groupBy('badge_type_id')->pluck('qty', 'badge_type_id');
        $perRecipient = $activeRows->groupBy('teacher_id')->map(fn ($rows) => [
            'ops' => $rows->map($batchKey)->unique()->count(),
            'qty' => (int) $rows->sum('quantity'),
            // كمية كل نوع على حدة — لأعمدة الأنواع بجانب الإجمالي في جدول «حسب المستلم»
            'types' => $rows->groupBy('badge_type_id')->map(fn ($tRows) => (int) $tRows->sum('quantity')),
        ]);

        // قسم «حسب المستلم» في صفحة الإحصائيات يعرض أعلى 10 مستلمين بإجمالي
        // المستلم (وعند التعادل بعدد العمليات) — القائمة الكاملة في صفحة
        // تفاصيل المستلمين المرتبطة بزر «عرض المزيد» في رأس القسم
        $perRecipient = $perRecipient
            ->sort(fn ($a, $b) => ($b['qty'] <=> $a['qty']) ?: ($b['ops'] <=> $a['ops']))
            ->take(10);

        // قائمة المستلمين للفلتر: من لهم تسليمات داخل النطاق المرئي (دون فلتر المستلم نفسه)
        $requestNoTeacher = clone $request;
        $requestNoTeacher->query->remove('teacher_id');
        $requestNoTeacher->request->remove('teacher_id');
        $recipients = User::whereIn('id',
            $this->handoverFilterQuery($requestNoTeacher, $access)->distinct()->pluck('teacher_id')
        )->orderBy('name')->get(['id', 'name']);

        return view('badges.handovers.statistics', [
            'totals' => [
                'received' => (int) ($statusSums['active']->qty ?? 0),
                'operations' => $activeRows->map($batchKey)->unique()->count(),
                'recipients' => $activeRows->pluck('teacher_id')->unique()->count(),
                'cancelled_qty' => (int) ($statusSums['cancelled']->qty ?? 0),
                'cancelled_records' => (int) ($statusSums['cancelled']->records ?? 0),
            ],
            'perType' => $perType,
            'perTypeCancelled' => $perTypeCancelled,
            'perRecipient' => $perRecipient,
            'recipientUsers' => User::whereIn('id', $perRecipient->keys())->get(['id', 'name'])->keyBy('id'),
            'recipients' => $recipients,
            'types' => BadgeType::orderBy('name')->get(),
        ]);
    }

    public function index(Request $request, BadgeAccessService $access)
    {
        $this->authorize('viewAny', BadgeTeacherHandover::class);

        $user = $request->user();
        $base = $this->handoverFilterQuery($request, $access);

        // التجميع حسب الدفعة: صفوف القائمة عمليات تسليم لا أنواعًا مفردة
        // (التسليمات القديمة بلا batch_id تُعامل كلٌّ منها كعملية مستقلة)
        $batchKeys = (clone $base)->whereNotNull('batch_id')
            ->selectRaw('MAX(id) as max_id')->selectRaw('batch_id')->groupBy('batch_id')->get()
            ->map(fn ($r) => ['key' => $r->batch_id, 'max_id' => (int) $r->max_id]);
        $singleKeys = (clone $base)->whereNull('batch_id')->select('id')->get()
            ->map(fn ($r) => ['key' => 'single-' . $r->id, 'max_id' => (int) $r->id]);
        $allKeys = $batchKeys->concat($singleKeys)->sortByDesc('max_id')->values();

        $perPage = 20;
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $pageKeys = $allKeys->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $pageKeys, $allKeys->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $batchIds = $pageKeys->pluck('key')->reject(fn ($k) => str_starts_with($k, 'single-'))->values();
        $singleIds = $pageKeys->pluck('key')->filter(fn ($k) => str_starts_with($k, 'single-'))
            ->map(fn ($k) => (int) substr($k, 7))->values();
        $items = (clone $base)
            ->with(['teacher', 'badgeType', 'creator', 'sourceUser'])
            ->where(fn ($q) => $q->whereIn('batch_id', $batchIds)->orWhereIn('id', $singleIds))
            ->latest('id')
            ->get();
        $groups = $pageKeys->map(fn ($k) => [
            'key' => $k['key'],
            'items' => $items->filter(fn ($h) => ($h->batch_id ?? 'single-' . $h->id) === $k['key'])->values(),
        ])->filter(fn ($g) => $g['items']->isNotEmpty())->values();

        return view('badges.handovers.index', [
            'groups' => $groups,
            'paginator' => $paginator,
            // فلتر المستلمين: من يستطيع تسليمهم + نفسه (كمستلم)
            'teachers' => $access->selectableRecipients($user)->push($user),
            'types' => BadgeType::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request, BadgeAccessService $access, BadgeBalanceService $balances)
    {
        $this->authorize('create', BadgeTeacherHandover::class);

        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');

        return view('badges.handovers.create', [
            // المستلمون المسموحون لهذا المُسلِّم (رتبة أقل + نطاقه)
            'teachers' => $access->selectableRecipients($user),
            'types' => BadgeType::active()->orderBy('name')->get(),
            // الأدمن يسلّم من رصيد الإدارة، وغيره من رصيد عهدته
            'adminBalances' => $isAdmin ? $balances->adminBalances() : $balances->teacherBalances($user->id),
            'balanceLabel' => $isAdmin ? 'رصيد الإدارة' : 'رصيد عهدتك',
            'access' => $access,
            // تعبئة مسبقة اختيارية (مثال: زر «تسليم» في صفحة النواقص يعبّئ
            // المستلم وكميات عجزه) — تُستخدم فقط عند غياب old input
            'prefillTeacherId' => $request->filled('teacher_id') ? (int) $request->teacher_id : null,
            'prefillQuantities' => collect((array) $request->query('quantities', []))
                ->map(fn ($q) => (int) $q)->all(),
        ]);
    }

    public function store(StoreBadgeTeacherHandoverRequest $request, BadgeTeacherHandoverService $service)
    {
        $teacher = User::findOrFail($request->teacher_id);

        // تسليم كل الأنواع المطلوبة دفعة واحدة (ذرّي — إما الكل أو لا شيء)
        $handovers = $service->handoverMultiple(
            $teacher,
            (array) $request->input('quantities', []),
            $request->handover_date,
            $request->user(),
            $request->notes,
        );

        return redirect()->route('badges.handovers.index')
            ->with('success', 'تم تسليم الأوسمة للمعلم بنجاح (' . count($handovers) . ' من الأنواع).');
    }

    public function cancel(CancelBadgeOperationRequest $request, BadgeTeacherHandover $handover, BadgeTeacherHandoverService $service)
    {
        $this->authorize('cancel', $handover);

        $service->cancel($handover, $request->user(), $request->reason);

        return redirect()->route('badges.handovers.index')->with('success', 'تم إلغاء التسليم بنجاح.');
    }
}
