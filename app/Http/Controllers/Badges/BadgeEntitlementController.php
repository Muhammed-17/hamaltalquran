<?php

namespace App\Http\Controllers\Badges;

use App\Http\Controllers\Controller;
use App\Http\Requests\Badges\CancelBadgeOperationRequest;
use App\Http\Requests\Badges\StoreBadgeDailyEntitlementRequest;
use App\Http\Requests\Badges\StoreBadgeEntitlementRequest;
use App\Http\Requests\Badges\UpdateBadgeEntitlementRequest;
use App\Models\Badges\BadgeEntitlement;
use App\Models\Badges\BadgeType;
use App\Models\Student;
use App\Services\Badges\BadgeAccessService;
use App\Services\Badges\BadgeBalanceService;
use App\Services\Badges\BadgeEntitlementService;
use App\Services\UserAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BadgeEntitlementController extends Controller
{
    public function index(Request $request, UserAccessService $access, BadgeBalanceService $balances)
    {
        $this->authorize('viewAny', BadgeEntitlement::class);

        $user = $request->user();
        $circleIds = $access->accessibleCircles($user)->pluck('id');

        $applyScope = function ($q) use ($user, $circleIds) {
            if (!$user->hasRole(['admin', 'general_manager'])) {
                $q->where(fn ($w) => $w->whereIn('circle_id', $circleIds)->orWhere('teacher_id', $user->id));
            }
        };

        $base = BadgeEntitlement::with(['student', 'circle', 'teacher', 'badgeType'])
            ->tap($applyScope)
            ->when($request->filled('circle_id'), fn ($q) => $q->where('circle_id', $request->circle_id))
            ->when($request->filled('badge_type_id'), fn ($q) => $q->where('badge_type_id', $request->badge_type_id))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', (int) $request->teacher_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('month') && preg_match('/^\d{4}-\d{2}$/', (string) $request->month), function ($q) use ($request) {
                [$year, $month] = explode('-', (string) $request->month);
                $q->whereYear('entitlement_date', (int) $year)->whereMonth('entitlement_date', (int) $month);
            })
            ->when($request->filled('q'), fn ($q) => $q->whereHas('student', fn ($s) => $s->withoutGlobalScopes()->where('name', 'like', "%{$request->q}%")));

        // قائمة المسؤولين عن التوزيع الظاهرين في النطاق (لقائمة التصفية)
        $distributors = \App\Models\User::whereIn('id',
            BadgeEntitlement::query()->tap($applyScope)->distinct()->pluck('teacher_id')
        )->orderBy('name')->get(['id', 'name']);

        // التجميع حسب الدفعة: صفوف القائمة عمليات إعطاء لا استحقاقات مفردة
        // (الاستحقاقات القديمة بلا batch_id تُعامل كلٌّ منها كعملية مستقلة)
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
            ->where(fn ($q) => $q->whereIn('batch_id', $batchIds)->orWhereIn('id', $singleIds))
            ->latest('id')
            ->get();
        $groups = $pageKeys->map(fn ($k) => [
            'key' => $k['key'],
            'items' => $items->filter(fn ($e) => ($e->batch_id ?? 'single-' . $e->id) === $k['key'])->values(),
        ])->filter(fn ($g) => $g['items']->isNotEmpty())->values();

        // رصيد كل موزّع من نوع استحقاقه (لنموذج «توزيع المتبقي» داخل القائمة)
        $distributorBalances = [];
        foreach ($items as $e) {
            $key = $e->teacher_id . '_' . $e->badge_type_id;
            $distributorBalances[$key] ??= $balances->distributorBalance($e->teacher_id, $e->badge_type_id);
        }

        return view('badges.entitlements.index', [
            'groups' => $groups,
            'paginator' => $paginator,
            'distributorBalances' => $distributorBalances,
            // عهدة المشاهد نفسه (لنموذج الإرجاع الذاتي): [badge_type_id => balance] بالموجب فقط
            'returnBalances' => $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher'])
                ? collect($balances->teacherBalances($user->id))->filter(fn ($q) => (int) $q > 0)
                : collect(),
            'distributors' => $distributors,
            'circles' => $access->accessibleCircles($user)->get(),
            'types' => BadgeType::orderBy('name')->get(),
        ]);
    }

    /**
     * صفحة التسليم اليومي (تحل محل نموذج إنشاء الاستحقاق): ورقة عمل يوم
     * (تاريخ + حلقة + موزّع) — صف لكل طالب مقيّد وعمود لكل نوع نشط،
     * والخانة تحمل إجمالي ما سُجّل للطالب من النوع في هذا اليوم عند هذا
     * الموزّع، بجانب إجمالي مستحقه ومسلّمه ومتبقّيه عبر كل الأيام.
     */
    public function create(Request $request, UserAccessService $access, BadgeAccessService $badgeAccess, BadgeBalanceService $balances)
    {
        $this->authorize('create', BadgeEntitlement::class);

        $user = $request->user();
        $date = (string) $request->get('date', now()->toDateString());
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = now()->toDateString();
        }

        $circles = $access->accessibleCircles($user)->get();
        $circle = $circles->firstWhere('id', (int) $request->get('circle_id')) ?? $circles->first();
        $types = BadgeType::active()->orderBy('name')->get();

        $distributors = collect();
        $distributor = null;
        $students = collect();
        $distributorBalances = [];
        $sheet = [];

        if ($circle) {
            // الموزّعون المرشحون للحلقة: معلمو الحلقة والفرع + المستخدم الحالي إن كان مسؤولًا عنها
            $teacherUserIds = \App\Models\Teacher::whereIn('id', collect()
                ->merge(DB::table('circle_teacher')->where('circle_id', $circle->id)->pluck('teacher_id'))
                ->merge(DB::table('branch_teacher')->where('branch_id', $circle->branch_id)->pluck('teacher_id'))
                ->unique())->pluck('user_id');
            if ($badgeAccess->staffResponsibleForCircle($user, $circle->id)) {
                $teacherUserIds->push($user->id);
            }
            $distributors = \App\Models\User::whereIn('id', $teacherUserIds->unique())
                ->orderBy('name')->get(['id', 'name']);
            $distributor = $distributors->firstWhere('id', (int) $request->get('teacher_id'))
                ?? $distributors->firstWhere('id', $user->id)
                ?? $distributors->first();

            $students = Student::withoutGlobalScopes()
                ->where('circle_id', $circle->id)
                ->where('status', 'مقيد')
                ->orderBy('name')
                ->get(['id', 'name']);

            if ($distributor) {
                $distributorBalances = $balances->distributorBalances($distributor->id);

                $ents = BadgeEntitlement::query()
                    ->where('circle_id', $circle->id)
                    ->where('teacher_id', $distributor->id)
                    ->where('status', '!=', \App\Enums\Badges\EntitlementStatus::Cancelled->value)
                    ->get(['id', 'student_id', 'badge_type_id', 'quantity', 'entitlement_date']);
                $entIds = $ents->pluck('id');
                $deliveredByEnt = \App\Models\Badges\BadgeDistribution::active()
                    ->whereIn('entitlement_id', $entIds)
                    ->selectRaw('entitlement_id, SUM(quantity) as qty')
                    ->groupBy('entitlement_id')->pluck('qty', 'entitlement_id');
                $deliveredTodayByEnt = \App\Models\Badges\BadgeDistribution::active()
                    ->whereIn('entitlement_id', $entIds)
                    ->whereDate('distribution_date', $date)
                    ->selectRaw('entitlement_id, SUM(quantity) as qty')
                    ->groupBy('entitlement_id')->pluck('qty', 'entitlement_id');

                foreach ($ents as $ent) {
                    $cell = &$sheet[$ent->student_id][$ent->badge_type_id];
                    $cell['total'] = ($cell['total'] ?? 0) + (int) $ent->quantity;
                    $cell['delivered'] = ($cell['delivered'] ?? 0) + (int) ($deliveredByEnt[$ent->id] ?? 0);
                    $cell['delivered_today'] = ($cell['delivered_today'] ?? 0) + (int) ($deliveredTodayByEnt[$ent->id] ?? 0);
                    if ($ent->entitlement_date?->toDateString() === $date) {
                        $cell['today'] = ($cell['today'] ?? 0) + (int) $ent->quantity;
                    }
                    unset($cell);
                }
                foreach ($sheet as &$typesOfStudent) {
                    foreach ($typesOfStudent as &$cell) {
                        $cell['remaining'] = max(0, ($cell['total'] ?? 0) - ($cell['delivered'] ?? 0));
                    }
                }
                unset($typesOfStudent, $cell);
            }
        }

        return view('badges.entitlements.create', [
            'date' => $date,
            'circles' => $circles,
            'circle' => $circle,
            'types' => $types,
            'distributors' => $distributors,
            'distributor' => $distributor,
            'students' => $students,
            'distributorBalances' => $distributorBalances,
            'sheet' => $sheet,
        ]);
    }

    /** حفظ صفحة التسليم اليومي: تُطبَّق فروقات الصفوف المعدّلة فقط، بترتيب تعديلها */
    public function storeDaily(StoreBadgeDailyEntitlementRequest $request, BadgeEntitlementService $service)
    {
        $order = array_values(array_filter(array_map('intval', explode(',', (string) $request->input('order', '')))));

        $result = $service->processDailySheet(
            $request->only(['date', 'circle_id', 'teacher_id']),
            (array) $request->input('rows', []),
            $request->boolean('instant'),
            $request->user(),
            $order,
            (array) $request->input('deliver', []),
        );

        $params = $request->only(['date', 'circle_id', 'teacher_id']);

        if ($result['students'] === 0 && $result['floored'] === []) {
            return redirect()->route('badges.entitlements.create', $params)
                ->with('success', 'لا توجد تعديلات — لم يُسجَّل شيء.');
        }

        // تعذّر تطبيق كل شيء (حذف/تقليل محظور فقط): نبقى في الورقة مع التفاصيل
        if ($result['students'] === 0) {
            return redirect()->route('badges.entitlements.create', $params)
                ->with('error', 'تعذّر تطبيق التعديلات — التفاصيل في التنبيه بالأسفل.')
                ->with('sheet_floored', $result['floored']);
        }

        $message = 'تم حفظ صفحة التسليم اليومي: ' . $result['students'] . ' طالب';
        if ($result['created'] > 0) {
            $message .= '، ' . $result['created'] . ' استحقاق جديد';
        }
        if ($result['distributed'] > 0) {
            $message .= '، وسُلّم ' . $result['distributed'] . ' وسامًا';
        }
        $message .= '.';

        // زر «تسليم فوري» في البطاقة يبقى في الورقة لخدمة الطالب التالي
        if ($request->boolean('stay')) {
            $redirect = redirect()->route('badges.entitlements.create', $params)->with('success', $message);
            if ($result['floored'] !== []) {
                $redirect->with('sheet_floored', $result['floored']);
            }

            return $redirect;
        }

        // الحفظ الجماعي: انتقال لقائمة الاستحقاقات كما في حفظ الغياب، والتنبيهات في رسالة مرافقة
        $redirect = redirect()->route('badges.entitlements.index')->with('success', $message);
        if ($result['floored'] !== []) {
            $lines = collect($result['floored'])
                ->map(fn ($f) => $f['student'] . ' — ' . $f['type'] . ': بمقدار ' . $f['quantity'])
                ->implode('؛ ');
            $redirect->with('error', 'تعذّر تقليل كميات سُلّمت فعليًا: ' . $lines);
        }

        return $redirect;
    }

    /**
     * استعلام الاستحقاقات داخل نطاق المستخدم ومصفّى بفلاتر الصفحة
     * (بحث/حلقة/مسؤول/نوع/حالة/شهر) — أساس مشترك للإحصائيات وملف التقرير.
     */
    private function entitlementFilterQuery(Request $request, UserAccessService $access)
    {
        $user = $request->user();
        $circleIds = $access->accessibleCircles($user)->pluck('id');

        return BadgeEntitlement::query()
            ->when(!$user->hasRole(['admin', 'general_manager']), function ($q) use ($circleIds, $user) {
                $q->where(fn ($w) => $w->whereIn('circle_id', $circleIds)->orWhere('teacher_id', $user->id));
            })
            ->when($request->filled('circle_id'), fn ($q) => $q->where('circle_id', (int) $request->circle_id))
            ->when($request->filled('badge_type_id'), fn ($q) => $q->where('badge_type_id', (int) $request->badge_type_id))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', (int) $request->teacher_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('month') && preg_match('/^\d{4}-\d{2}$/', (string) $request->month), function ($q) use ($request) {
                [$year, $month] = explode('-', (string) $request->month);
                $q->whereYear('entitlement_date', (int) $year)->whereMonth('entitlement_date', (int) $month);
            })
            ->when($request->filled('q'), fn ($q) => $q->whereHas('student', fn ($s) => $s->withoutGlobalScopes()->where('name', 'like', "%{$request->q}%")));
    }

    /** صفحة إحصائيات الاستحقاقات والتوزيعات — كل أرقامها حسب الفلاتر الحالية */
    public function statistics(Request $request, UserAccessService $access)
    {
        $this->authorize('viewAny', BadgeEntitlement::class);

        $base = $this->entitlementFilterQuery($request, $access);
        $ids = (clone $base)->select('id');

        // عدّادات الحالات تُحسب من نفس الفلاتر لكن دون فلتر الحالة نفسه
        $requestNoStatus = clone $request;
        $requestNoStatus->query->remove('status');
        $requestNoStatus->request->remove('status');
        $statusCounts = $this->entitlementFilterQuery($requestNoStatus, $access)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        $totalEntitled = (int) (clone $base)->sum('quantity');
        $totalDelivered = (int) \App\Models\Badges\BadgeDistribution::active()
            ->whereIn('entitlement_id', $ids)->sum('quantity');
        // المستحق المستهلك بالاستبدال بلعبة لم يعد متبقيًا على المركز
        $redeemedShortage = (int) \App\Models\Badges\BadgeRedemptionItem::where('shortage_quantity', '>', 0)
            ->whereIn('entitlement_id', $ids)
            ->whereHas('redemption', fn ($q) => $q->where('status', 'completed'))
            ->sum('shortage_quantity');
        $totalRemaining = $totalEntitled - $totalDelivered - $redeemedShortage;

        $perTypeEntitled = (clone $base)
            ->selectRaw('badge_type_id, COUNT(*) as ops, SUM(quantity) as qty')
            ->groupBy('badge_type_id')->get()->keyBy('badge_type_id');
        $perTypeDelivered = \App\Models\Badges\BadgeDistribution::active()
            ->whereIn('entitlement_id', $ids)
            ->selectRaw('badge_type_id, SUM(quantity) as qty')
            ->groupBy('badge_type_id')->pluck('qty', 'badge_type_id');

        $perDistributorEntitled = (clone $base)
            ->selectRaw('teacher_id, COUNT(*) as ops, SUM(quantity) as qty')
            ->groupBy('teacher_id')->get()->keyBy('teacher_id');
        $perDistributorDelivered = \App\Models\Badges\BadgeDistribution::active()
            ->whereIn('entitlement_id', $ids)
            ->selectRaw('teacher_id, SUM(quantity) as qty')
            ->groupBy('teacher_id')->pluck('qty', 'teacher_id');

        // أكثر الطلاب حصولًا على الأوسمة (بإجمالي الموزّع فعليًا) لقسم الإحصائيات
        $topStudents = array_slice($this->statisticsMatrix($request, $access, 'student_id'), 0, 10, true);
        $topStudentModels = \App\Models\Student::withoutGlobalScopes()->with('circle:id,name')
            ->whereIn('id', array_keys($topStudents))->get()->keyBy('id');

        $user = $request->user();
        $distributors = \App\Models\User::whereIn('id',
            BadgeEntitlement::query()
                ->when(!$user->hasRole(['admin', 'general_manager']), function ($q) use ($access, $user) {
                    $q->whereIn('circle_id', $access->accessibleCircles($user)->pluck('id'))->orWhere('teacher_id', $user->id);
                })
                ->distinct()->pluck('teacher_id')
        )->orderBy('name')->get(['id', 'name']);

        return view('badges.entitlements.statistics', [
            'totals' => [
                'entitled' => $totalEntitled,
                'delivered' => $totalDelivered,
                'remaining' => $totalRemaining,
                'pct' => $totalEntitled > 0 ? (int) round($totalDelivered / $totalEntitled * 100) : 0,
                'batches' => (clone $base)->pluck('batch_id', 'id')
                    ->map(fn ($b, $id) => $b ?? 'single-' . $id)->unique()->count(),
                'entitlements' => (clone $base)->count(),
                'completed' => (int) ($statusCounts['completed'] ?? 0),
                'partial' => (int) ($statusCounts['partial'] ?? 0),
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'shortage_qty' => (int) \App\Models\Badges\BadgeShortage::open()
                    ->whereIn('entitlement_id', $ids)->sum('shortage_quantity'),
            ],
            'perTypeEntitled' => $perTypeEntitled,
            'perTypeDelivered' => $perTypeDelivered,
            'perDistributorEntitled' => $perDistributorEntitled,
            'perDistributorDelivered' => $perDistributorDelivered,
            'topStudents' => $topStudents,
            'topStudentModels' => $topStudentModels,
            'types' => BadgeType::orderBy('name')->get(),
            'distributors' => $distributors,
            'distributorUsers' => \App\Models\User::whereIn('id', $perDistributorEntitled->keys())->get(['id', 'name'])->keyBy('id'),
            'circles' => $access->accessibleCircles($user)->get(),
        ]);
    }

    /**
     * مصفوفة (مستحق/موزّع) لكل نوع، مجمّعة بعمود تجميع (teacher_id للموزّعين أو
     * student_id للطلاب) ومرتّبة بإجمالي الموزّع تنازليًا — حسب فلاتر الإحصائيات.
     *
     * @return array<int, array{entitled: array<int, int>, delivered: array<int, int>, total_entitled: int, total_delivered: int}>
     */
    private function statisticsMatrix(Request $request, UserAccessService $access, string $groupColumn): array
    {
        $base = $this->entitlementFilterQuery($request, $access);
        $ids = (clone $base)->select('id');

        $rows = [];
        (clone $base)
            ->selectRaw("{$groupColumn} as gid, badge_type_id, SUM(quantity) as qty")
            ->groupBy('gid', 'badge_type_id')->get()
            ->each(function ($r) use (&$rows) {
                $rows[(int) $r->gid]['entitled'][(int) $r->badge_type_id] = (int) $r->qty;
            });
        \App\Models\Badges\BadgeDistribution::active()
            ->whereIn('entitlement_id', $ids)
            ->selectRaw("{$groupColumn} as gid, badge_type_id, SUM(quantity) as qty")
            ->groupBy('gid', 'badge_type_id')->get()
            ->each(function ($r) use (&$rows) {
                $rows[(int) $r->gid]['delivered'][(int) $r->badge_type_id] = (int) $r->qty;
            });
        // المستحق المستهلك بالاستبدال لكل مجموعة (يُخصم من المتبقي لا من المستحق)
        \App\Models\Badges\BadgeRedemptionItem::query()
            ->join('badge_entitlements', 'badge_entitlements.id', '=', 'badge_redemption_items.entitlement_id')
            ->whereIn('badge_redemption_items.entitlement_id', $ids)
            ->where('badge_redemption_items.shortage_quantity', '>', 0)
            ->whereHas('redemption', fn ($q) => $q->where('status', 'completed'))
            ->selectRaw("badge_entitlements.{$groupColumn} as gid, SUM(badge_redemption_items.shortage_quantity) as qty")
            ->groupBy('gid')->get()
            ->each(function ($r) use (&$rows) {
                $rows[(int) $r->gid]['redeemed'] = (int) $r->qty;
            });

        foreach ($rows as &$row) {
            $row += ['entitled' => [], 'delivered' => [], 'redeemed' => 0];
            $row['total_entitled'] = array_sum($row['entitled']);
            $row['total_delivered'] = array_sum($row['delivered']);
        }
        unset($row);

        uasort($rows, fn ($a, $b) => [$b['total_delivered'], $b['total_entitled']] <=> [$a['total_delivered'], $a['total_entitled']]);

        return $rows;
    }

    /** الأنواع الظاهرة فعليًا في مصفوفة إحصائية (بها بيانات) مرتّبة بالاسم */
    private function matrixTypes(array $matrix): \Illuminate\Database\Eloquent\Collection
    {
        $usedTypeIds = collect($matrix)
            ->flatMap(fn ($r) => array_merge(array_keys($r['entitled']), array_keys($r['delivered'])))
            ->unique()->all();

        return BadgeType::whereIn('id', $usedTypeIds)->orderBy('name')->get();
    }

    /** قوائم فلاتر صفحات الإحصائيات التفصيلية (المسؤولون والحلقات والأنواع) */
    private function statisticsFilterData(Request $request, UserAccessService $access): array
    {
        $user = $request->user();
        $distributors = \App\Models\User::whereIn('id',
            BadgeEntitlement::query()
                ->when(!$user->hasRole(['admin', 'general_manager']), function ($q) use ($access, $user) {
                    $q->whereIn('circle_id', $access->accessibleCircles($user)->pluck('id'))->orWhere('teacher_id', $user->id);
                })
                ->distinct()->pluck('teacher_id')
        )->orderBy('name')->get(['id', 'name']);

        return [
            'distributors' => $distributors,
            'circles' => $access->accessibleCircles($user)->get(),
            'filterTypes' => BadgeType::orderBy('name')->get(),
        ];
    }

    /** طلب المصفوفة دون حقل البحث (يُطبَّق البحث بالاسم على الصفوف نفسها) */
    private function matrixRequest(Request $request): Request
    {
        $clone = clone $request;
        $clone->query->remove('q');
        $clone->request->remove('q');

        return $clone;
    }

    /** صفحة تفاصيل «حسب المسؤول عن التوزيع»: المستحق والموزّع لكل نوع لكل مسؤول */
    public function statisticsDistributors(Request $request, UserAccessService $access)
    {
        $this->authorize('viewAny', BadgeEntitlement::class);

        $matrix = $this->statisticsMatrix($this->matrixRequest($request), $access, 'teacher_id');
        $users = \App\Models\User::whereIn('id', array_keys($matrix))->get(['id', 'name'])->keyBy('id');
        if ($request->filled('q')) {
            $q = (string) $request->q;
            $matrix = array_filter($matrix, fn ($row, $gid) => isset($users[$gid]) && mb_stripos($users[$gid]->name, $q) !== false, ARRAY_FILTER_USE_BOTH);
        }

        return view('badges.entitlements.statistics-distributors', [
            'matrix' => $matrix,
            'types' => $this->matrixTypes($matrix),
            'users' => $users,
        ] + $this->statisticsFilterData($request, $access));
    }

    /** صفحة تفاصيل الطلاب: المستحق والموزّع لكل نوع لكل طالب مع حلقته */
    public function statisticsStudents(Request $request, UserAccessService $access)
    {
        $this->authorize('viewAny', BadgeEntitlement::class);

        $matrix = $this->statisticsMatrix($this->matrixRequest($request), $access, 'student_id');
        $students = \App\Models\Student::withoutGlobalScopes()->with('circle:id,name')
            ->whereIn('id', array_keys($matrix))->get()->keyBy('id');
        if ($request->filled('q')) {
            $q = (string) $request->q;
            $matrix = array_filter($matrix, fn ($row, $gid) => isset($students[$gid]) && mb_stripos($students[$gid]->name, $q) !== false, ARRAY_FILTER_USE_BOTH);
        }

        return view('badges.entitlements.statistics-students', [
            'matrix' => $matrix,
            'types' => $this->matrixTypes($matrix),
            'students' => $students,
        ] + $this->statisticsFilterData($request, $access));
    }

    /** زر التقرير: ملف Excel بالاستحقاقات والتوزيعات حسب الفلاتر الحالية */
    public function report(Request $request, UserAccessService $access)
    {
        $base = fn () => $this->entitlementFilterQuery($request, $access);

        $month = $request->filled('month') ? (string) $request->month : 'all';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BadgeEntitlementsReportExport($base),
            "entitlements-report-{$month}.xlsx"
        );
    }

    public function store(StoreBadgeEntitlementRequest $request, BadgeEntitlementService $service)
    {
        $instant = $request->boolean('instant');

        $result = $service->createMultiple(
            $request->only(['student_id', 'circle_id', 'teacher_id', 'entitlement_date', 'notes']),
            (array) $request->input('quantities', []),
            $instant,
            $request->user(),
        );

        $message = 'تم تسجيل الاستحقاقات بنجاح (' . count($result['entitlements']) . ' من الأنواع).';
        if ($instant && $result['distributed'] > 0) {
            $message .= ' وتم تسليم ' . $result['distributed'] . ' وسامًا للطالب فورًا.';
        }

        return redirect()->route('badges.entitlements.index')->with('success', $message);
    }

    public function edit(BadgeEntitlement $entitlement)
    {
        $this->authorize('update', $entitlement);

        return view('badges.entitlements.edit', compact('entitlement'));
    }

    public function update(UpdateBadgeEntitlementRequest $request, BadgeEntitlement $entitlement, BadgeEntitlementService $service)
    {
        $service->update($entitlement, $request->only(['quantity', 'entitlement_date', 'notes']), $request->user());

        return redirect()->route('badges.entitlements.index')->with('success', 'تم تحديث الاستحقاق بنجاح.');
    }

    public function cancel(CancelBadgeOperationRequest $request, BadgeEntitlement $entitlement, BadgeEntitlementService $service)
    {
        $this->authorize('cancel', $entitlement);

        $service->cancel($entitlement, $request->user(), $request->reason);

        return redirect()->route('badges.entitlements.index')->with('success', 'تم إلغاء الاستحقاق بنجاح.');
    }
}
