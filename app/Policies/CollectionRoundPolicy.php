<?php

namespace App\Policies;

use App\Models\CollectionRound;
use App\Models\User;
use App\Services\UserAccessService;

class CollectionRoundPolicy
{
    public function __construct(protected UserAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('view collection rounds');
    }

    public function view(User $user, CollectionRound $round): bool
    {
        if ($user->hasAnyRole(['admin', 'general_manager'])) {
            return true;
        }

        if (! $user->can('view collection rounds')) {
            return false;
        }

        return $this->userCanAccessCircle($user, $round->circle_id);
    }

    public function create(User $user): bool
    {
        return $user->can('create collection rounds');
    }

    public function update(User $user, CollectionRound $round): bool
    {
        if (! $user->can('edit collection rounds')) {
            return false;
        }

        if ($round->status === 'confirmed') {
            return $user->hasRole('admin');
        }

        if ($user->hasAnyRole(['admin', 'general_manager'])) {
            return true;
        }

        // المدير مقيد بحلقات نطاقه (كان يعدّل جولات أي مركز)
        if ($user->hasRole('manager')) {
            return $this->userCanAccessCircle($user, $round->circle_id);
        }

        return $this->userCanAccessCircle($user, $round->circle_id) && $round->status === 'pending';
    }

    public function delete(User $user, CollectionRound $round): bool
    {
        if (! $user->can('delete collection rounds')) {
            return false;
        }

        if ($round->status === 'confirmed') {
            return $user->hasRole('admin');
        }

        if ($user->hasAnyRole(['admin', 'general_manager'])) {
            return true;
        }

        // المدير مقيد بحلقات نطاقه (كان يحذف جولات أي مركز)
        if ($user->hasRole('manager')) {
            return $this->userCanAccessCircle($user, $round->circle_id);
        }

        return $this->userCanAccessCircle($user, $round->circle_id) && $round->status === 'pending';
    }

    public function confirm(User $user, CollectionRound $round): bool
    {
        if (! $user->can('confirm collection rounds')) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'general_manager'])) {
            return true;
        }

        // المدير يؤكد جولات حلقاته فقط (كان يؤكد جولات أي مركز)
        return $user->hasRole('manager')
            && $this->userCanAccessCircle($user, $round->circle_id);
    }

    private function userCanAccessCircle(User $user, int $circleId): bool
    {
        return $this->access->canAccessCircle($user, $circleId);
    }
}
