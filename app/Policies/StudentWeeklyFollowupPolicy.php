<?php

namespace App\Policies;

use App\Models\StudentWeeklyFollowup;
use App\Models\User;
use App\Services\UserAccessService;

/**
 * Policy: StudentWeeklyFollowup
 *
 * Controls access based on permissions and circle assignments.
 */
class StudentWeeklyFollowupPolicy
{
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
    public function view(User $user, StudentWeeklyFollowup $studentWeeklyFollowup): bool
    {
        if (!$user->hasAnyRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher'])) {
            return false;
        }

        if ($user->hasRole(['admin', 'general_manager'])) {
            return true;
        }

        $access = app(UserAccessService::class);
        $circleIds = $this->accessibleCircleIds($user, $access);

        return $this->planIsAccessible($studentWeeklyFollowup, $circleIds);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create student weekly followups');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StudentWeeklyFollowup $studentWeeklyFollowup): bool
    {
        if (!$user->can('edit student weekly followups')) {
            return false;
        }

        if ($user->hasRole(['admin', 'general_manager'])) {
            return true;
        }

        $access = app(UserAccessService::class);

        if ($user->hasRole(['manager', 'supervisor'])) {
            $circleIds = $this->accessibleCircleIds($user, $access);
            return $this->planIsAccessible($studentWeeklyFollowup, $circleIds);
        }

        // Teacher can only update their own records
        $teacher = $access->teacher($user);
        return $teacher?->id === $studentWeeklyFollowup->teacher_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StudentWeeklyFollowup $studentWeeklyFollowup): bool
    {
        if (!$user->can('delete student weekly followups')) {
            return false;
        }

        if ($user->hasRole(['admin', 'general_manager'])) {
            return true;
        }

        $access = app(UserAccessService::class);

        if ($user->hasRole(['manager', 'supervisor'])) {
            $circleIds = $this->accessibleCircleIds($user, $access);
            return $this->planIsAccessible($studentWeeklyFollowup, $circleIds);
        }

        $teacher = $access->teacher($user);
        return $teacher?->id === $studentWeeklyFollowup->teacher_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StudentWeeklyFollowup $studentWeeklyFollowup): bool
    {
        return $user->hasRole(['admin', 'general_manager']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StudentWeeklyFollowup $studentWeeklyFollowup): bool
    {
        return $user->hasRole(['admin', 'general_manager']);
    }

    // ==========================================
    // Helpers
    // ==========================================

    /**
     * يجمع دوائر الوصول المتاحة للمستخدم حسب دوره (manager / supervisor / teacher).
     */
    private function accessibleCircleIds(User $user, UserAccessService $access)
    {
        if ($user->hasRole('manager')) {
            return $access->managerCircleIds($user);
        }

        if ($user->hasRole('supervisor')) {
            return $access->supervisorCircleIds($user);
        }

        return $access->teacherCircleIdsWithinCenter($user);
    }

    /**
     * يتحقق هل المتابعة متاحة ضمن الحلقات المسموح بها، سواء:
     * - متابعة جماعية أو فردية مرتبطة مباشرة بـ circle_id
     * - متابعة فردية مرتبطة بطالب ينتمي لإحدى هذه الحلقات
     */
    private function planIsAccessible(StudentWeeklyFollowup $plan, $circleIds): bool
    {
        if ($circleIds->isEmpty()) {
            return false;
        }

        if ($plan->circle_id && $circleIds->contains($plan->circle_id)) {
            return true;
        }

        if ($plan->student_id && $plan->student && $circleIds->contains($plan->student->circle_id)) {
            return true;
        }

        return false;
    }
}
