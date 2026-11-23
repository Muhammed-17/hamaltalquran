<?php

namespace App\Policies\Badges;

use App\Models\Badges\BadgeType;
use App\Models\User;

class BadgeTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, BadgeType $type): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, BadgeType $type): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, BadgeType $type): bool
    {
        // لا حذف لنوع مستخدم فعليًا في أي حركة/عملية
        return $user->hasRole('admin')
            && $type->stockMovements()->doesntExist()
            && $type->handovers()->doesntExist()
            && $type->entitlements()->doesntExist()
            && $type->distributions()->doesntExist();
    }
}
