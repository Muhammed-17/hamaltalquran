<?php

namespace App\Policies\Badges;

use App\Models\Badges\BadgeTeacherHandover;
use App\Models\User;
use App\Services\Badges\BadgeAccessService;

class BadgeTeacherHandoverPolicy
{
    public function __construct(private BadgeAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('badges.handovers.view');
    }

    public function view(User $user, BadgeTeacherHandover $handover): bool
    {
        return $user->can('badges.handovers.view')
            && $this->access->teacherInScope($user, $handover->teacher_id);
    }

    public function create(User $user): bool
    {
        return $user->can('badges.handovers.create');
    }

    public function cancel(User $user, BadgeTeacherHandover $handover): bool
    {
        return $user->can('badges.handovers.cancel')
            && $this->access->teacherInScope($user, $handover->teacher_id);
    }
}
