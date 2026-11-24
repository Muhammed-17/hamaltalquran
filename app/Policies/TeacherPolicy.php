<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

/**
 * قسم المعلمين لمدير النظام فقط — حُوّلت صلاحياته إلى دور بقراره،
 * فكل فحوصات السياسة أصبحت بالدور مباشرة (مع بقاء حماية عدم حذف/إيقاف النفس).
 */
class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Teacher $teacher): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Teacher $teacher): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Teacher $teacher): bool
    {
        if ($user->id === $teacher->user_id) return false;

        return $user->hasRole('admin');
    }

    public function toggle(User $user, Teacher $teacher): bool
    {
        if ($user->id === $teacher->user_id) return false;

        return $user->hasRole('admin');
    }
}
