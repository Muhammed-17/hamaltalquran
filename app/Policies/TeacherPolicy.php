<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;
use App\Services\UserAccessService;

class TeacherPolicy
{
    public function __construct(protected UserAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('view teachers') || $user->hasRole(['admin', 'general_manager']);
    }

    public function view(User $user, Teacher $teacher): bool
    {
        if (!$user->can('view teachers')) return false;
        if ($user->hasRole(['admin', 'general_manager'])) return true;

        $record = $this->access->teacher($user);
        if (!$record) return false;

        if ($user->hasRole('teacher')) {
            return $user->id === $teacher->user_id;
        }

        if ($user->hasRole('supervisor') && !$user->hasRole(['manager', 'teacher', 'admin', 'general_manager'])) {
            return $user->id === $teacher->user_id;
        }

        return $this->access->accessibleTeachers($user)
            ->whereKey($teacher->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('create teachers');
    }

    public function update(User $user, Teacher $teacher): bool
    {
        if (!$user->can('edit teachers')) return false;
        if ($user->hasRole(['admin', 'general_manager'])) return true;

        $record = $this->access->teacher($user);
        if (!$record) return false;

        if ($user->hasRole('teacher')) {
            return $user->id === $teacher->user_id;
        }

        if ($user->hasRole('supervisor') && !$user->hasRole(['manager', 'teacher', 'admin', 'general_manager'])) {
            return $user->id === $teacher->user_id;
        }

        return $record->branch?->center_id !== null && $teacher->branch?->center_id === $record->branch?->center_id;
    }

    public function delete(User $user, Teacher $teacher): bool
    {
        if (!$user->can('delete teachers')) return false;
        if ($user->id === $teacher->user_id) return false;

        if ($teacher->user->hasRole(['admin', 'general_manager']) && !$user->hasRole('admin')) {
            return false;
        }

        if ($user->hasRole(['admin', 'general_manager'])) return true;

        $record = $this->access->teacher($user);
        if (!$record) return false;

        if ($user->hasRole('teacher') && !$user->hasRole(['manager', 'admin', 'general_manager'])) {
            return false;
        }

        if ($user->hasRole('supervisor') && !$user->hasRole(['manager', 'teacher', 'admin', 'general_manager'])) {
            return false;
        }

        return $record->branch?->center_id !== null && $teacher->branch?->center_id === $record->branch?->center_id;
    }

    public function toggle(User $user, Teacher $teacher): bool
    {
        if (!$user->can('toggle teacher status')) return false;
        if ($user->id === $teacher->user_id) return false;

        if ($user->hasRole(['admin', 'general_manager'])) return true;

        if ($teacher->user->hasRole(['admin', 'general_manager'])) return false;

        $record = $this->access->teacher($user);
        if (!$record) return false;

        if ($user->hasRole('teacher') && !$user->hasRole(['manager', 'admin', 'general_manager'])) {
            return false;
        }

        if ($user->hasRole('supervisor') && !$user->hasRole(['manager', 'teacher', 'admin', 'general_manager'])) {
            return false;
        }

        return $record->branch?->center_id !== null && $teacher->branch?->center_id === $record->branch?->center_id;
    }
}
