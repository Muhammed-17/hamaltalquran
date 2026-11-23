<?php

namespace App\Policies\Badges;

use App\Models\Badges\BadgeStockMovement;
use App\Models\User;

class BadgeStockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, BadgeStockMovement $movement): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher']);
    }
}
