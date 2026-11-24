<?php

namespace App\Policies;

use App\Models\SubscriptionPrice;
use App\Models\User;

class SubscriptionPricePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, SubscriptionPrice $price): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, SubscriptionPrice $price): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, SubscriptionPrice $price): bool
    {
        return $user->hasRole('admin');
    }
}