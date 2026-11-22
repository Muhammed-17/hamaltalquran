<?php

namespace App\Http\Controllers\Badges;

use App\Http\Controllers\Controller;
use App\Http\Requests\Badges\CancelBadgeOperationRequest;
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
            $distributorBalances[$key] ??= $balances->teacherBalance($e->teacher_id, $e->badge_type_id);
        }

        return view('badges.entitlements.index', [
            'groups' => $groups,
            'paginator' => $paginator,
            'distributorBalances' => $distributorBalances,
            'distributors' => $distributors,
            'circles' => $access->accessibleCircles($user)->get(),
            'types' => BadgeType::orderBy('name')->get(),
        ]);
    }

    public function create(UserAccessService $access)
    {
        $this->authorize('create', BadgeEntitlement::class);

        return view('badges.entitlements.create', [
            'circles' => $access->accessibleCircles(auth()->user())->get(),
            'types' => BadgeType::active()->orderBy('name')->get(),
        ]);
    }

    /** طلاب الحلقة + المسؤولون عنها + أرصدة عهدتهم لكل نوع (لنموذج الإنشاء الموحد) */
    public function circleData(Request $request, BadgeAccessService $badgeAccess, BadgeBalanceService $balances)
    {
        $user = $request->user();
        abort_unless($user->can('badges.entitlements.create') || $user->can('badges.collections.create'), 403);

        $circleId = (int) $request->get('circle_id');
        abort_unless($circleId && $badgeAccess->canAccessCircle($user, $circleId), 403);

        $students = Student::withoutGlobalScopes()
            ->where('circle_id', $circleId)
            ->where('status', 'مقيد')
            ->orderBy('name')
            ->get(['id', 'name']);

        $teacherRecordIds = collect()
            ->merge(DB::table('circle_teacher')->where('circle_id', $circleId)->pluck('teacher_id'))
            ->merge(DB::table('branch_teacher')->where('branch_id', \App\Models\Circle::find($circleId)?->branch_id)->pluck('teacher_id'))
            ->unique();

        $teachers = \App\Models\Teacher::with('user:id,name')
            ->whereIn('id', $teacherRecordIds)
            ->get()
            ->map(fn ($t) => ['id' => $t->user_id, 'name' => $t->user?->name])
            ->filter(fn ($t) => $t['name'])
            ->values();

        // أرصدة العهدة لكل مسؤول + المستخدم الحالي (لخيار «أنا»)، تظهر بجانب كل نوع في النموذج
        $balancesByUser = [];
        foreach ($teachers as $t) {
            $balancesByUser[$t['id']] = $balances->teacherBalances($t['id']);
        }
        $balancesByUser[$user->id] = $balances->teacherBalances($user->id);

        return response()->json(['students' => $students, 'teachers' => $teachers, 'balances' => $balancesByUser]);
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
        $totalRemaining = $totalEntitled - $totalDelivered;

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
            'types' => BadgeType::orderBy('name')->get(),
            'distributors' => $distributors,
            'distributorUsers' => \App\Models\User::whereIn('id', $perDistributorEntitled->keys())->get(['id', 'name'])->keyBy('id'),
            'circles' => $access->accessibleCircles($user)->get(),
        ]);
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

    public function show(BadgeEntitlement $entitlement, BadgeBalanceService $balances)
    {
        $this->authorize('view', $entitlement);

        $entitlement->load(['student', 'circle', 'teacher', 'badgeType', 'creator', 'shortage',
            'distributions' => fn ($q) => $q->with('creator')->latest('id')]);

        return view('badges.entitlements.show', [
            'entitlement' => $entitlement,
            'teacherBalance' => $balances->teacherBalance($entitlement->teacher_id, $entitlement->badge_type_id),
            'delivered' => $entitlement->deliveredQuantity(),
            'remaining' => $entitlement->remainingQuantity(),
        ]);
    }

    public function edit(BadgeEntitlement $entitlement)
    {
        $this->authorize('update', $entitlement);

        return view('badges.entitlements.edit', compact('entitlement'));
    }

    public function update(UpdateBadgeEntitlementRequest $request, BadgeEntitlement $entitlement, BadgeEntitlementService $service)
    {
        $service->update($entitlement, $request->only(['quantity', 'entitlement_date', 'notes']), $request->user());

        return redirect()->route('badges.entitlements.show', $entitlement)->with('success', 'تم تحديث الاستحقاق بنجاح.');
    }

    public function cancel(CancelBadgeOperationRequest $request, BadgeEntitlement $entitlement, BadgeEntitlementService $service)
    {
        $this->authorize('cancel', $entitlement);

        $service->cancel($entitlement, $request->user(), $request->reason);

        return redirect()->route('badges.entitlements.index')->with('success', 'تم إلغاء الاستحقاق بنجاح.');
    }
}
