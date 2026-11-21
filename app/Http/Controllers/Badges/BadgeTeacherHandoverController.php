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
        $visibleIds = $access->selectableRecipients($user)->pluck('id')->push($user->id);

        $handovers = BadgeTeacherHandover::with(['teacher', 'badgeType', 'creator', 'sourceUser'])
            ->when(!$user->hasRole(['admin', 'general_manager']), fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('teacher_id', $visibleIds)
                ->orWhere('source_user_id', $user->id)))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->teacher_id))
            ->when($request->filled('badge_type_id'), fn ($q) => $q->where('badge_type_id', $request->badge_type_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('badges.handovers.index', [
            'handovers' => $handovers,
            // فلتر المستلمين: من يستطيع تسليمهم + نفسه (كمستلم)
            'teachers' => $access->selectableRecipients($user)->push($user),
            'types' => BadgeType::orderBy('name')->get(),
        ]);
    }

    public function create(BadgeAccessService $access, BadgeBalanceService $balances)
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
