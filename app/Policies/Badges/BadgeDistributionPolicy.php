<?php

namespace App\Policies\Badges;

use App\Models\Badges\BadgeDistribution;
use App\Models\Badges\BadgeEntitlement;
use App\Models\User;
use App\Services\Badges\BadgeAccessService;

class BadgeDistributionPolicy
{
    public function __construct(private BadgeAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('badges.entitlements.view');
    }

    public function view(User $user, BadgeDistribution $distribution): bool
    {
        return $user->can('badges.entitlements.view')
            && ($distribution->teacher_id === $user->id
                || $this->access->canAccessCircle($user, $distribution->entitlement->circle_id));
    }

    /**
     * التوزيع يُخصم من عهدة معلم الاستحقاق: إما أن يكون المنفذ هو المعلم
     * نفسه، أو مشرفًا/مديرًا على الحلقة (يسجل نيابة عنه).
     */
    public function create(User $user, ?BadgeEntitlement $entitlement = null): bool
    {
        if (!$user->can('badges.entitlements.create')) {
            return false;
        }
        if (!$entitlement) {
            return true;
        }
        if ($entitlement->teacher_id === $user->id) {
            return true;
        }

        return $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor'])
            && $this->access->canAccessCircle($user, $entitlement->circle_id);
    }

    public function cancel(User $user, BadgeDistribution $distribution): bool
    {
        return $user->can('badges.entitlements.cancel')
            && ($distribution->teacher_id === $user->id
                || $this->access->canAccessCircle($user, $distribution->entitlement->circle_id));
    }
}
