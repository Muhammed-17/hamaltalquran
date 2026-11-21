<?php

namespace App\Http\Controllers\Badges;

use App\Http\Controllers\Controller;
use App\Http\Requests\Badges\CancelBadgeOperationRequest;
use App\Http\Requests\Badges\StoreBadgeDistributionRequest;
use App\Models\Badges\BadgeDistribution;
use App\Models\Badges\BadgeEntitlement;
use App\Services\Badges\BadgeDistributionService;
use App\Services\UserAccessService;
use Illuminate\Http\Request;

class BadgeDistributionController extends Controller
{
    public function index(Request $request, UserAccessService $access)
    {
        $this->authorize('viewAny', BadgeDistribution::class);

        $user = $request->user();
        $circleIds = $access->accessibleCircles($user)->pluck('id');

        $distributions = BadgeDistribution::with(['student', 'teacher', 'badgeType', 'entitlement.circle'])
            ->when(!$user->hasRole(['admin', 'general_manager']), function ($q) use ($circleIds, $user) {
                $q->where(fn ($w) => $w->where('teacher_id', $user->id)
                    ->orWhereHas('entitlement', fn ($e) => $e->whereIn('circle_id', $circleIds)));
            })
            ->when($request->filled('badge_type_id'), fn ($q) => $q->where('badge_type_id', $request->badge_type_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('student', fn ($s) => $s->withoutGlobalScopes()->where('name', 'like', "%{$request->q}%")))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('badges.distributions.index', [
            'distributions' => $distributions,
            'types' => \App\Models\Badges\BadgeType::orderBy('name')->get(),
        ]);
    }

    public function store(StoreBadgeDistributionRequest $request, BadgeDistributionService $service)
    {
        $entitlement = BadgeEntitlement::findOrFail($request->entitlement_id);

        $distribution = $service->distribute(
            $entitlement,
            (int) $request->quantity,
            $request->distribution_date,
            $request->user(),
            $request->notes,
        );

        return redirect()->route('badges.entitlements.show', $entitlement)
            ->with('success', 'تم تسجيل التوزيع بنجاح.');
    }

    public function show(BadgeDistribution $distribution)
    {
        $this->authorize('view', $distribution);

        return view('badges.distributions.show', [
            'distribution' => $distribution->load(['student', 'teacher', 'badgeType', 'entitlement.circle', 'creator', 'shortage']),
            'collected' => $distribution->collectedQuantity(),
        ]);
    }

    public function cancel(CancelBadgeOperationRequest $request, BadgeDistribution $distribution, BadgeDistributionService $service)
    {
        $this->authorize('cancel', $distribution);

        $service->cancel($distribution, $request->user(), $request->reason);

        return redirect()->route('badges.entitlements.show', $distribution->entitlement_id)
            ->with('success', 'تم إلغاء التوزيع بنجاح.');
    }
}
