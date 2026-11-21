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
    public function index(Request $request, BadgeAccessService $access)
    {
        $this->authorize('viewAny', BadgeTeacherHandover::class);

        $user = $request->user();
        $teacherIds = $access->selectableTeachers($user)->pluck('id');

        $handovers = BadgeTeacherHandover::with(['teacher', 'badgeType', 'creator'])
            ->when(!$user->hasRole(['admin', 'general_manager']), fn ($q) => $q->whereIn('teacher_id', $teacherIds))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->teacher_id))
            ->when($request->filled('badge_type_id'), fn ($q) => $q->where('badge_type_id', $request->badge_type_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('badges.handovers.index', [
            'handovers' => $handovers,
            'teachers' => $access->selectableTeachers($user),
            'types' => BadgeType::orderBy('name')->get(),
        ]);
    }

    public function create(BadgeAccessService $access, BadgeBalanceService $balances)
    {
        $this->authorize('create', BadgeTeacherHandover::class);

        return view('badges.handovers.create', [
            'teachers' => $access->selectableTeachers(auth()->user()),
            'types' => BadgeType::active()->orderBy('name')->get(),
            'adminBalances' => $balances->adminBalances(),
        ]);
    }

    public function store(StoreBadgeTeacherHandoverRequest $request, BadgeTeacherHandoverService $service)
    {
        $teacher = User::findOrFail($request->teacher_id);
        $type = BadgeType::findOrFail($request->badge_type_id);

        $service->handover($teacher, $type, (int) $request->quantity, $request->handover_date, $request->user(), $request->notes);

        return redirect()->route('badges.handovers.index')->with('success', 'تم تسليم الأوسمة للمعلم بنجاح.');
    }

    public function show(BadgeTeacherHandover $handover, BadgeBalanceService $balances)
    {
        $this->authorize('view', $handover);

        return view('badges.handovers.show', [
            'handover' => $handover->load(['teacher', 'badgeType', 'creator']),
            'teacherBalance' => $balances->teacherBalance($handover->teacher_id, $handover->badge_type_id),
            'adminBalance' => $balances->adminBalance($handover->badge_type_id),
        ]);
    }

    public function cancel(CancelBadgeOperationRequest $request, BadgeTeacherHandover $handover, BadgeTeacherHandoverService $service)
    {
        $this->authorize('cancel', $handover);

        $service->cancel($handover, $request->user(), $request->reason);

        return redirect()->route('badges.handovers.index')->with('success', 'تم إلغاء التسليم بنجاح.');
    }
}
