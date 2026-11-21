<?php

namespace App\Policies\Badges;

use App\Models\Badges\BadgeCollectionItem;
use App\Models\User;
use App\Services\Badges\BadgeAccessService;

class BadgeCollectionItemPolicy
{
    public function __construct(private BadgeAccessService $access) {}

    public function view(User $user, BadgeCollectionItem $item): bool
    {
        return $user->can('badges.collections.view')
            && ($item->round->teacher_id === $user->id
                || $this->access->canAccessCircle($user, $item->round->circle_id));
    }

    public function update(User $user, BadgeCollectionItem $item): bool
    {
        return $user->can('badges.collections.update')
            && $item->round->isDraft()
            && ($item->round->teacher_id === $user->id
                || $this->access->canAccessCircle($user, $item->round->circle_id));
    }
}
