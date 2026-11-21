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
    public function index(Request $request, UserAccessService $access)
    {
        $this->authorize('viewAny', BadgeShortage::class);

        $user = $request->user();
        $circleIds = $access->accessibleCircles($user)->pluck('id');

        $shortages = BadgeShortage::with(['student', 'teacher', 'badgeType', 'entitlement.circle'])
            ->when(!$user->hasRole(['admin', 'general_manager']), function ($q) use ($circleIds, $user) {
                $q->where(fn ($w) => $w->where('teacher_id', $user->id)
                    ->orWhereHas('entitlement', fn ($e) => $e->whereIn('circle_id', $circleIds)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status), fn ($q) => $q->open())
            ->when($request->filled('badge_type_id'), fn ($q) => $q->where('badge_type_id', $request->badge_type_id))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->teacher_id))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('student', fn ($s) => $s->withoutGlobalScopes()->where('name', 'like', "%{$request->q}%")))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('badges.shortages.index', [
            'shortages' => $shortages,
            'types' => BadgeType::orderBy('name')->get(),
        ]);
    }

    public function show(BadgeShortage $shortage, BadgeBalanceService $balances)
    {
        $this->authorize('view', $shortage);

        $shortage->load(['student', 'teacher', 'badgeType', 'entitlement.circle',
            'entitlement.distributions' => fn ($q) => $q->latest('id')]);

        return view('badges.shortages.show', [
            'shortage' => $shortage,
            'teacherBalance' => $balances->teacherBalance($shortage->teacher_id, $shortage->badge_type_id),
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
