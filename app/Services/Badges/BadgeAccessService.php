<?php

namespace App\Services\Badges;

use App\Models\Circle;
use App\Models\Teacher;
use App\Models\User;
use App\Services\UserAccessService;
use Illuminate\Support\Facades\DB;

/**
 * نطاق الوصول الخاص بنظام الأوسمة، مبني على UserAccessService العام:
 * - الوصول لحلقة = canAccessCircle (معلمها/مشرف فرعها/مدير مركزها/إدارة)
 * - مسؤولية معلم عن حلقة = معلم رئيسي/مساعد فيها أو مشرف على فرعها
 */
class BadgeAccessService
{
    public function __construct(private UserAccessService $access) {}

    public function canAccessCircle(User $user, int $circleId): bool
    {
        return $this->access->canAccessCircle($user, $circleId);
    }

    /** هل المعلم (users.id) مسؤول عن هذه الحلقة؟ */
    public function teacherResponsibleForCircle(int $teacherUserId, int $circleId): bool
    {
        $teacher = Teacher::where('user_id', $teacherUserId)->first();
        if (!$teacher) {
            return false;
        }

        $circle = Circle::find($circleId);
        if (!$circle) {
            return false;
        }

        $teachesCircle = DB::table('circle_teacher')
            ->where('teacher_id', $teacher->id)
            ->where('circle_id', $circleId)
            ->exists();

        if ($teachesCircle) {
            return true;
        }

        // مشرف على فرع الحلقة
        return $circle->branch_id && DB::table('branch_teacher')
            ->where('teacher_id', $teacher->id)
            ->where('branch_id', $circle->branch_id)
            ->exists();
    }

    /** هل يقع هذا المعلم (users.id) ضمن نطاق وصول المستخدم؟ */
    public function teacherInScope(User $user, int $teacherUserId): bool
    {
        if ($user->hasRole(['admin', 'general_manager'])) {
            return true;
        }
        if ($user->id === $teacherUserId) {
            return true;
        }

        return $this->access->accessibleTeachers($user)
            ->where('user_id', $teacherUserId)
            ->exists();
    }

    /** قائمة المعلمين (مستخدمين) القابلين للاختيار في النماذج حسب نطاق المستخدم */
    public function selectableTeachers(User $user)
    {
        if ($user->hasRole(['admin', 'general_manager'])) {
            return User::whereHas('teacher')->orderBy('name')->get(['id', 'name']);
        }

        $teacherIds = $this->access->accessibleTeachers($user)->pluck('user_id');
        if (!$teacherIds->contains($user->id) && $user->teacher) {
            $teacherIds->push($user->id);
        }

        return User::whereIn('id', $teacherIds)->orderBy('name')->get(['id', 'name']);
    }
}
