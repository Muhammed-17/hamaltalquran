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
    public function index(Request $request, UserAccessService $access)
    {
        $this->authorize('viewAny', BadgeEntitlement::class);

        $user = $request->user();
        $circleIds = $access->accessibleCircles($user)->pluck('id');

        $entitlements = BadgeEntitlement::with(['student', 'circle', 'teacher', 'badgeType'])
            ->when(!$user->hasRole(['admin', 'general_manager']), function ($q) use ($circleIds, $user) {
                $q->where(fn ($w) => $w->whereIn('circle_id', $circleIds)->orWhere('teacher_id', $user->id));
            })
            ->when($request->filled('circle_id'), fn ($q) => $q->where('circle_id', $request->circle_id))
            ->when($request->filled('badge_type_id'), fn ($q) => $q->where('badge_type_id', $request->badge_type_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('student', fn ($s) => $s->withoutGlobalScopes()->where('name', 'like', "%{$request->q}%")))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('badges.entitlements.index', [
            'entitlements' => $entitlements,
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

    /** طلاب الحلقة + المعلمون المسؤولون عنها (لنموذج الإنشاء) */
    public function circleData(Request $request, BadgeAccessService $badgeAccess)
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

        return response()->json(['students' => $students, 'teachers' => $teachers]);
    }

    public function store(StoreBadgeEntitlementRequest $request, BadgeEntitlementService $service)
    {
        $entitlement = $service->create($request->only([
            'student_id', 'circle_id', 'teacher_id', 'badge_type_id',
            'quantity', 'entitlement_date', 'notes',
        ]), $request->user());

        return redirect()->route('badges.entitlements.show', $entitlement)
            ->with('success', 'تم تسجيل الاستحقاق بنجاح.');
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
