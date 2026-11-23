<?php

namespace App\Policies\Badges;

use App\Models\Badges\BadgeCollectionItem;
use App\Models\User;
use App\Services\Badges\BadgeAccessService;

/** بنود التحصيل تتبع قاعدة الجولات: إدارة التحصيل فقط، في كل الفروع والمراكز */
class BadgeCollectionItemPolicy
{
    public function __construct(private BadgeAccessService $access) {}

    public function view(User $user, BadgeCollectionItem $item): bool
    {
        return $this->access->canRunCollections($user);
    }

    public function update(User $user, BadgeCollectionItem $item): bool
    {
        return $this->access->canRunCollections($user)
            && $item->round->isDraft();
    }
}
