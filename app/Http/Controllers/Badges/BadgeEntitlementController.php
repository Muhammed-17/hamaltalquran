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

        $base = BadgeEntitlement::with(['student', 'circle', 'teacher', 'badgeType'])
            ->when(!$user->hasRole(['admin', 'general_manager']), function ($q) use ($circleIds, $user) {
                $q->where(fn ($w) => $w->whereIn('circle_id', $circleIds)->orWhere('teacher_id', $user->id));
            })
            ->when($request->filled('circle_id'), fn ($q) => $q->where('circle_id', $request->circle_id))
            ->when($request->filled('badge_type_id'), fn ($q) => $q->where('badge_type_id', $request->badge_type_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('student', fn ($s) => $s->withoutGlobalScopes()->where('name', 'like', "%{$request->q}%")));

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
