<?php

namespace App\Http\Controllers\Badges;

use App\Http\Controllers\Controller;
use App\Http\Requests\Badges\ResolveBadgeShortageRequest;
use App\Models\Badges\BadgeShortage;
use App\Models\Badges\BadgeType;
use App\Services\Badges\BadgeBalanceService;
use App\Services\Badges\BadgeDistributionService;
use App\Services\UserAccessService;
use Illuminate\Http\Request;

class BadgeShortageController extends Controller
{
    public function index(Request $request, UserAccessService $access, \App\Services\Badges\BadgeAccessService $badgeAccess, BadgeBalanceService $balances)
    {
        $this->authorize('viewAny', BadgeShortage::class);

        $user = $request->user();

        $scoped = BadgeShortage::query()
            ->when(!$user->hasRole(['admin', 'general_manager']), function ($q) use ($badgeAccess, $user) {
                // كل شخص يرى عجزه وعجز من تحته في سلسلة العهدة فقط
                // (من يستطيع التسليم لهم = رتبة أقل وداخل نطاقه) + عجزه هو
                $visibleIds = $badgeAccess->selectableRecipients($user)->pluck('id')->push($user->id);
                $q->whereIn('teacher_id', $visibleIds);
            })
            ->open(); // تُعرض السجلات المفتوحة فقط (لا فلتر حالة — الصفحة للمفتوح دائمًا)

        // خيارات فلتر الموزّع: من لديهم عجز مفتوح داخل نطاق الرؤية الحالي
        $filterTeachers = \App\Models\User::whereIn('id', (clone $scoped)->distinct()->pluck('teacher_id'))
            ->orderBy('name')->get();

        // الفلاتر تُطبق قبل التجميع حسب الموزّع (الشهر والحلقة على الاستحقاق المرتبط)
        $base = (clone $scoped)
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->teacher_id))
            ->when($request->filled('badge_type_id'), fn ($q) => $q->where('badge_type_id', (int) $request->badge_type_id))
            ->when($request->filled('circle_id'), fn ($q) => $q->whereHas('entitlement', fn ($e) => $e->where('circle_id', (int) $request->circle_id)))
            ->when($request->filled('month') && preg_match('/^\d{4}-\d{2}$/', (string) $request->month), function ($q) use ($request) {
                [$y, $m] = explode('-', (string) $request->month);
                $q->whereHas('entitlement', fn ($e) => $e->whereYear('entitlement_date', (int) $y)->whereMonth('entitlement_date', (int) $m));
            })
            ->when($request->filled('q'), fn ($q) => $q->whereHas('student', fn ($s) => $s->withoutGlobalScopes()->where('name', 'like', "%{$request->q}%")));

        // التجميع حسب الموزّع: بطاقة لكل موزّع بإجمالي عجزه، والطلاب
        // المستحقون داخل البطاقة يظهرون بزر «عرض الطلاب»
        $teacherKeys = (clone $base)->selectRaw('teacher_id, MAX(id) as max_id')->groupBy('teacher_id')->get()
            ->map(fn ($r) => ['key' => (int) $r->teacher_id, 'max_id' => (int) $r->max_id])
            ->sortByDesc('max_id')->values();

        $perPage = 20;
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $pageKeys = $teacherKeys->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $pageKeys, $teacherKeys->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $items = (clone $base)->with(['student', 'teacher', 'badgeType', 'entitlement.circle'])
            ->whereIn('teacher_id', $pageKeys->pluck('key'))
            ->latest('id')->get();
        $canCreateHandover = $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor']);
        $groups = $pageKeys->map(function ($k) use ($items, $user, $badgeAccess, $canCreateHandover, $balances) {
            $groupItems = $items->where('teacher_id', $k['key'])->values();
            $teacher = $groupItems->first()?->teacher;

            // بيانات زر «تسليم الطالب»: أرصدة الموزّع الحالية، ولكل طالب هل
            // يستطيع المستخدم الحالي استكمال سجلاته وهل يوجد رصيد قابل للتسليم
            $typeBalances = $groupItems->pluck('badge_type_id')->unique()
                ->mapWithKeys(fn ($typeId) => [$typeId => $teacher ? $balances->distributorBalance($teacher->id, $typeId) : 0]);
            $studentMeta = $groupItems->groupBy('student_id')->map(fn ($rows) => [
                'resolvable' => $rows->every(fn ($s) => $user->can('resolve', $s)),
                'deliverable' => $rows->contains(fn ($s) => min((int) $s->shortage_quantity, (int) ($typeBalances[$s->badge_type_id] ?? 0)) > 0),
            ]);
            // زر «تسليم»: يظهر فقط لمن يستطيع فعلًا التسليم لهذا الموزّع
            // (صلاحية + رتبة/نطاق سلسلة العهدة)، ويعبّئ نموذج التسليم بكميات عجزه
            $canHandover = $canCreateHandover && $teacher && $badgeAccess->canHandoverTo($user, $teacher);

            return [
                'teacher' => $teacher,
                'items' => $groupItems,
                'studentMeta' => $studentMeta,
                'canHandover' => $canHandover,
                'handoverUrl' => $canHandover ? route('badges.handovers.create', [
                    'teacher_id' => $teacher->id,
                    'quantities' => $groupItems->groupBy('badge_type_id')
                        ->map(fn ($rows) => (int) $rows->sum('shortage_quantity'))->all(),
                ]) : null,
            ];
        })->filter(fn ($g) => $g['items']->isNotEmpty())->values();

        return view('badges.shortages.index', [
            'groups' => $groups,
            'paginator' => $paginator,
            'totalRecords' => (clone $base)->count(),
            // أعمدة جدول الطلاب: الأنواع الموجودة في مجموعات هذه الصفحة
            'typesInScope' => BadgeType::whereIn('id', $items->pluck('badge_type_id')->unique())->orderBy('name')->get(),
            'filterTeachers' => $filterTeachers,
            'filterCircles' => $access->accessibleCircles($user)->get(),
            'filterTypes' => BadgeType::orderBy('name')->get(),
        ]);
    }

    /**
     * زر «تسليم الطالب» في قائمة النواقص: يوزّع كل أنواع عجز الطالب المفتوحة
     * عند هذا الموزّع دفعة واحدة — لكل نوع: الأقل من (الناقص، رصيد الموزّع
     * الحالي) — بتاريخ اليوم وبلا نموذج، في معاملة واحدة.
     */
    /**
     * صفحة «تسليم المستحق» (من زر «تسليم» عند الطالب في قائمة النواقص):
     * متبقّيات الطالب المفتوحة عند موزّع واحد، لكل نوع: المتبقّي ورصيد
     * الموزّع وخانة «يُسلَّم الآن» مليئة بالقدر القابل للتسليم.
     */
    public function deliver(Request $request, BadgeBalanceService $balances)
    {
        $request->validate([
            'teacher_id' => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
        ]);

        $shortages = BadgeShortage::open()
            ->where('teacher_id', (int) $request->teacher_id)
            ->where('student_id', (int) $request->student_id)
            ->with(['student', 'teacher', 'badgeType'])
            ->get();

        if ($shortages->isEmpty()) {
            return redirect()->route('badges.shortages.index')
                ->with('success', 'لا توجد أوسمة ناقصة لهذا الطالب عند هذا الموزّع.');
        }

        // نفس بوابة الاستكمال لكل سجل قبل العرض أو التسليم
        foreach ($shortages as $shortage) {
            $this->authorize('resolve', $shortage);
        }

        $rows = $shortages->groupBy('badge_type_id')->map(function ($items) use ($balances) {
            $first = $items->first();
            $remaining = (int) $items->sum('shortage_quantity');
            $balance = $balances->distributorBalance($first->teacher_id, $first->badge_type_id);

            return [
                'type'        => $first->badgeType,
                'remaining'   => $remaining,
                'balance'     => $balance,
                'deliverable' => min($remaining, max(0, $balance)),
            ];
        })->values();

        return view('badges.shortages.deliver', [
            'student'     => $shortages->first()->student,
            'distributor' => $shortages->first()->teacher,
            'rows'        => $rows,
        ]);
    }

    /** حفظ صفحة «تسليم المستحق»: توزيع الكميات المدخلة على أقدم الاستحقاقات أولًا */
    public function deliverStore(Request $request, BadgeDistributionService $service, BadgeBalanceService $balances)
    {
        $request->validate([
            'teacher_id'   => ['required', 'integer'],
            'student_id'   => ['required', 'integer'],
            'quantities'   => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $shortages = BadgeShortage::open()
            ->where('teacher_id', (int) $request->teacher_id)
            ->where('student_id', (int) $request->student_id)
            ->with(['entitlement', 'badgeType'])
            ->get();

        foreach ($shortages as $shortage) {
            $this->authorize('resolve', $shortage);
        }

        $quantities = (array) $request->input('quantities', []);
        $distributed = 0;
        $shortfalls = [];
        \Illuminate\Support\Facades\DB::transaction(function () use ($shortages, $quantities, $service, $balances, $request, &$distributed, &$shortfalls) {
            foreach ($shortages->groupBy('badge_type_id') as $typeId => $items) {
                $want = (int) ($quantities[$typeId] ?? 0);
                if ($want <= 0) {
                    continue;
                }
                foreach ($items->sortBy('entitlement_id') as $shortage) {
                    if ($want <= 0) {
                        break;
                    }
                    $give = min($want, (int) $shortage->shortage_quantity, $balances->distributorBalance($shortage->teacher_id, (int) $typeId));
                    if ($give > 0) {
                        $service->distribute($shortage->entitlement, $give, now()->toDateString(), $request->user());
                        $distributed += $give;
                        $want -= $give;
                    }
                }
                if ($want > 0) {
                    $shortfalls[] = ($items->first()->badgeType?->name ?? '') . ': ' . $want;
                }
            }
        });

        $redirect = redirect()->route('badges.shortages.index')->with('success', $distributed > 0
            ? "تم تسليم {$distributed} وسامًا للطالب بنجاح."
            : 'لم يُسلَّم شيء — راجع الكميات المدخلة ورصيد الموزّع.');
        if ($shortfalls !== []) {
            $redirect->with('error', 'تعذّر تسليم الباقي لنفاد رصيد الموزّع: ' . implode('؛ ', $shortfalls));
        }

        return $redirect;
    }

    public function show(BadgeShortage $shortage, BadgeBalanceService $balances)
    {
        $this->authorize('view', $shortage);

        $shortage->load(['student', 'teacher', 'badgeType', 'entitlement.circle',
            'entitlement.distributions' => fn ($q) => $q->latest('id')]);

        return view('badges.shortages.show', [
            'shortage' => $shortage,
            'teacherBalance' => $balances->distributorBalance($shortage->teacher_id, $shortage->badge_type_id),
        ]);
    }

    /** استكمال التسليم: توزيع جديد على الاستحقاق المرتبط بالعجز */
    public function resolve(ResolveBadgeShortageRequest $request, BadgeShortage $shortage, BadgeDistributionService $service)
    {
        $service->distribute(
            $shortage->entitlement,
            (int) $request->quantity,
            $request->distribution_date,
            $request->user(),
            $request->notes,
        );

        return redirect()->route('badges.shortages.show', $shortage)
            ->with('success', 'تم تسجيل التسليم بنجاح.');
    }
}
