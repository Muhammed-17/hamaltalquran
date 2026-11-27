<?php

namespace App\Policies;

use App\Models\GroupSessionPlan;
use App\Models\User;
use App\Services\UserAccessService;

/**
 * Policy: GroupSessionPlan
 *
 * Controls access based on permissions and circle assignments.
 */
class GroupSessionPlanPolicy
{
    public function __construct(protected UserAccessService $access) {}

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GroupSessionPlan $groupSessionPlan): bool
    {
        if (!$user->hasAnyRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher'])) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $this->access->accessibleCircles($user)
            ->pluck('id')
            ->contains($groupSessionPlan->circle_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GroupSessionPlan $groupSessionPlan): bool
    {
        if (!$user->hasAnyRole(['admin', 'general_manager', 'manager', 'supervisor'])) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        // الخطة تخص حلقة: التعديل لمن تصل إليه هذه الحلقة من الأدوار المصرّح لها
        return $this->access->accessibleCircles($user)
            ->pluck('id')
            ->contains($groupSessionPlan->circle_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GroupSessionPlan $groupSessionPlan): bool
    {
        return $this->update($user, $groupSessionPlan);
    }


    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GroupSessionPlan $groupSessionPlan): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GroupSessionPlan $groupSessionPlan): bool
    {
        return $user->hasRole('admin');
    }

}
