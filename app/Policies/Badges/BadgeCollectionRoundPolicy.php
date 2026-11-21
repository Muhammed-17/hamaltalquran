<?php

namespace App\Policies\Badges;

use App\Models\Badges\BadgeCollectionRound;
use App\Models\User;
use App\Services\Badges\BadgeAccessService;

class BadgeCollectionRoundPolicy
{
    public function __construct(private BadgeAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('badges.collections.view');
    }

    public function view(User $user, BadgeCollectionRound $round): bool
    {
        return $user->can('badges.collections.view')
            && ($round->teacher_id === $user->id || $this->access->canAccessCircle($user, $round->circle_id));
    }

    public function create(User $user): bool
    {
        return $user->can('badges.collections.create');
    }

    public function update(User $user, BadgeCollectionRound $round): bool
    {
        return $user->can('badges.collections.update')
            && ($round->teacher_id === $user->id || $this->access->canAccessCircle($user, $round->circle_id));
    }

    public function complete(User $user, BadgeCollectionRound $round): bool
    {
        return $user->can('badges.collections.complete')
            && ($round->teacher_id === $user->id || $this->access->canAccessCircle($user, $round->circle_id));
    }

    public function cancel(User $user, BadgeCollectionRound $round): bool
    {
        return $user->can('badges.collections.cancel')
            && ($round->teacher_id === $user->id || $this->access->canAccessCircle($user, $round->circle_id));
    }
}
