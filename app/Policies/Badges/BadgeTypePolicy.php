<?php

namespace App\Policies\Badges;

use App\Models\Badges\BadgeType;
use App\Models\User;

class BadgeTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('badges.types.view');
    }

    public function view(User $user, BadgeType $type): bool
    {
        return $user->can('badges.types.view');
    }

    public function create(User $user): bool
    {
        return $user->can('badges.types.create');
    }

    public function update(User $user, BadgeType $type): bool
    {
        return $user->can('badges.types.update');
    }

    public function delete(User $user, BadgeType $type): bool
    {
        // لا حذف لنوع مستخدم فعليًا في أي حركة/عملية
        return $user->can('badges.types.delete')
            && $type->stockMovements()->doesntExist()
            && $type->handovers()->doesntExist()
            && $type->entitlements()->doesntExist()
            && $type->distributions()->doesntExist();
    }
}
