<?php

namespace App\Policies\Badges;

use App\Models\Badges\BadgeShortage;
use App\Models\User;
use App\Services\Badges\BadgeAccessService;

class BadgeShortagePolicy
{
    public function __construct(private BadgeAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('badges.shortages.view');
    }

    public function view(User $user, BadgeShortage $shortage): bool
    {
        return $user->can('badges.shortages.view')
            && ($shortage->teacher_id === $user->id
                || $this->access->canAccessCircle($user, $shortage->entitlement->circle_id));
    }

    /** الاستكمال = توزيع جديد على الاستحقاق المرتبط بالعجز */
    public function resolve(User $user, BadgeShortage $shortage): bool
    {
        return $user->can('badges.shortages.resolve')
            && ($shortage->teacher_id === $user->id
                || $this->access->canAccessCircle($user, $shortage->entitlement->circle_id));
    }
}
