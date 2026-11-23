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
        return $user->can('view students');
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->hasRole('guardian')) {
            return $user->can('view own children')
                && $student->guardian_id === $user->id
                && $student->status !== \App\Enums\StudentStatus::Stopped;
        }

        return $user->can('view students')
            && $this->canAccessStudent($user, $student);
    }

    public function create(User $user): bool
    {
        return $user->can('create students');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can('edit students')
            && $this->canAccessStudent($user, $student);
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->can('delete students')
            && $this->canAccessStudent($user, $student);
    }

    public function manageStatus(User $user, Student $student): bool
    {
        return $user->can('manage student status')
            && $this->canAccessStudent($user, $student);
    }

    public function assignCircle(User $user, Student $student): bool
    {
        return $user->can('assign student to circle')
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
