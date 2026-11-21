<?php

namespace App\Policies;

use App\Models\User as UserModel;
use App\Models\Teacher;
use App\Services\UserAccessService;

class UserPolicy
{
    public function __construct(protected UserAccessService $access) {}

    public function viewAny(UserModel $user): bool
    {
        return $user->can('view users');
    }

    public function view(UserModel $user, UserModel $model): bool
    {
        // كل مستخدم يشوف نفسه
        if ($user->id === $model->id) return true;
        if (!$user->can('view users')) return false;
        if ($user->hasRole('admin')) return true;

        // manager → مستخدمي فرعه بس (عبر سجل Teacher المرتبط بكل طرف)
        $record = $this->access->teacher($user);
        if (!$record) return false;

        $modelTeacher = Teacher::where('user_id', $model->id)->first();
        return $modelTeacher && $record->branch?->center_id !== null && $modelTeacher->branch?->center_id === $record->branch?->center_id;
    }

    public function create(UserModel $user, ?string $role = null): bool
    {
        if ($role === 'guardian') {
            return $user->can('manage guardians');
        }

        return $user->can('create users');
    }

    public function update(UserModel $user, UserModel $model): bool
    {
        // كل مستخدم يعدل نفسه — لو عنده permission
        if ($user->id === $model->id && $user->can('edit profile')) return true;
        if (!$user->can('edit users')) return false;
        if ($user->hasRole('admin')) return true;

        // manager → مستخدمي فرعه بس (عبر سجل Teacher المرتبط بكل طرف)
        $record = $this->access->teacher($user);
        if (!$record) return false;

        $modelTeacher = Teacher::where('user_id', $model->id)->first();
        return $modelTeacher && $record->branch?->center_id !== null && $modelTeacher->branch?->center_id === $record->branch?->center_id;
    }

    public function delete(UserModel $user, UserModel $model): bool
    {
        // مينفعش تحذف نفسك
        if ($user->id === $model->id) return false;
        return $user->hasRole('admin') && $user->can('delete users');
    }

    public function manageRoles(UserModel $user): bool
    {
        return $user->can('manage roles');
    }
}
