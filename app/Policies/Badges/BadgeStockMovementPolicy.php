<?php

namespace App\Policies\Badges;

use App\Models\Badges\BadgeStockMovement;
use App\Models\User;

class BadgeStockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('badges.stock.view') || $user->can('badges.stock.history');
    }

    public function view(User $user, BadgeStockMovement $movement): bool
    {
        return $user->can('badges.stock.history');
    }

    public function create(User $user): bool
    {
        return $user->can('badges.stock.add') || $user->can('badges.stock.adjust') || $user->can('badges.stock.return');
    }
}
