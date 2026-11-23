<?php

namespace App\Policies\Badges;

use App\Models\Badges\BadgeEntitlement;
use App\Models\User;
use App\Services\Badges\BadgeAccessService;

class BadgeEntitlementPolicy
{
    public function __construct(private BadgeAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('badges.entitlements.view');
    }

    public function create(User $user): bool
    {
        return $user->can('badges.entitlements.create');
    }

    public function update(User $user, BadgeEntitlement $entitlement): bool
    {
        return $user->can('badges.entitlements.update')
            && ($entitlement->teacher_id === $user->id || $this->access->canAccessCircle($user, $entitlement->circle_id));
    }

    public function cancel(User $user, BadgeEntitlement $entitlement): bool
    {
        return $user->can('badges.entitlements.cancel')
            && ($entitlement->teacher_id === $user->id || $this->access->canAccessCircle($user, $entitlement->circle_id));
    }
}
