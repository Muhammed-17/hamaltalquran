<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use App\Services\UserAccessService;

class StudentPolicy
{
    public function __construct(protected UserAccessService $access) {}

    private function canAccessStudent(User $user, Student $student): bool
    {
        return $this->access->canAccessStudent($user, $student);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher']);
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->hasRole('guardian')) {
            return $student->guardian_id === $user->id
                && $student->status !== \App\Enums\StudentStatus::Stopped;
        }

        return $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher'])
            && $this->canAccessStudent($user, $student);
    }

    public function create(User $user): bool
    {
        return $user->can('create student');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can('edit student')
            && $this->canAccessStudent($user, $student);
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->hasRole('admin')
            && $this->canAccessStudent($user, $student);
    }

    public function manageStatus(User $user, Student $student): bool
    {
        return $user->hasRole('admin')
            && $this->canAccessStudent($user, $student);
    }

    public function assignCircle(User $user, Student $student): bool
    {
        return $user->hasRole('admin')
            && $this->canAccessStudent($user, $student);
    }

    public function recordPayment(User $user, Student $student): bool
    {
        if ($student->status === \App\Enums\StudentStatus::Stopped) return false;
        if ($student->decision !== \App\Enums\StudentDecision::Accepted) return false;

        return $user->can('create subscriptions')
            && $this->canAccessStudent($user, $student);
    }

    public function notifyUnpaid(User $user, Student $student): bool
    {
        return $user->can('notify unpaid subscriptions')
            && $this->canAccessStudent($user, $student);
    }
}
