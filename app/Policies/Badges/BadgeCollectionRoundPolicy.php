<?php

namespace App\Policies\Badges;

use App\Models\Badges\BadgeCollectionRound;
use App\Models\User;
use App\Services\Badges\BadgeAccessService;

/**
 * جولات التحصيل وظيفة إدارية: مشرف/مدير/مدير عام/مدير نظام فقط، ويرون
 * كل الجولات في كل الفروع والمراكز — المعلم لا يصل إلى هذه الصفحات أصلًا.
 */
class BadgeCollectionRoundPolicy
{
    public function __construct(private BadgeAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->canRunCollections($user) && $user->can('badges.collections.view');
    }

    public function view(User $user, BadgeCollectionRound $round): bool
    {
        return $this->access->canRunCollections($user) && $user->can('badges.collections.view');
    }

    public function create(User $user): bool
    {
        return $this->access->canRunCollections($user) && $user->can('badges.collections.create');
    }

    public function update(User $user, BadgeCollectionRound $round): bool
    {
        return $this->access->canRunCollections($user) && $user->can('badges.collections.update');
    }

    public function complete(User $user, BadgeCollectionRound $round): bool
    {
        return $this->access->canRunCollections($user) && $user->can('badges.collections.complete');
    }

    public function cancel(User $user, BadgeCollectionRound $round): bool
    {
        return $this->access->canRunCollections($user) && $user->can('badges.collections.cancel');
    }
}
