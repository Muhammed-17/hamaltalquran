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
        // الرؤية بسلسلة العهدة: عجزه هو أو عجز من تحته (يستطيع التسليم له)،
        // والأدمن والمدير العام يريان الكل — منسجم مع قائمة النواقص
        return $user->can('badges.shortages.view')
            && ($user->hasRole(['admin', 'general_manager'])
                || $shortage->teacher_id === $user->id
                || ($shortage->teacher && $this->access->canHandoverTo($user, $shortage->teacher)));
    }

    /** الاستكمال = توزيع جديد على الاستحقاق المرتبط بالعجز */
    public function resolve(User $user, BadgeShortage $shortage): bool
    {
        return $user->can('badges.shortages.resolve')
            && ($shortage->teacher_id === $user->id
                || $this->access->canAccessCircle($user, $shortage->entitlement->circle_id));
    }
}
