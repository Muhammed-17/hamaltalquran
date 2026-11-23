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
        return $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor']);
    }

    public function view(User $user, BadgeTeacherHandover $handover): bool
    {
        return $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor'])
            && $this->access->teacherInScope($user, $handover->teacher_id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor']);
    }

    public function cancel(User $user, BadgeTeacherHandover $handover): bool
    {
        return $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor'])
            && $this->access->teacherInScope($user, $handover->teacher_id);
    }
}
