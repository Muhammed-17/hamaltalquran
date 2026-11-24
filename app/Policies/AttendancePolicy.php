<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use App\Services\UserAccessService;

class AttendancePolicy
{
    public function __construct(protected UserAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher']);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->hasRole('admin')) return true;

        if (!$user->hasRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher'])) return false;

        return $this->userCanManageAttendance($user, $attendance);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher']);
    }

    public function update(User $user, ?Attendance $attendance = null): bool
    {
        if (!$user->hasRole(['admin', 'general_manager', 'manager', 'supervisor'])) return false;

        // استدعاء من index (بدون instance)
        if ($attendance === null) {
            return true;
        }

        if ($user->hasRole('admin')) return true;

        return $this->userCanManageAttendance($user, $attendance);
    }

    public function delete(User $user, ?Attendance $attendance = null): bool
    {
        if (!$user->hasRole('admin')) return false;

        // استدعاء من index (بدون instance)
        if ($attendance === null) {
            return true;
        }

        return true;
    }

    private function userCanManageAttendance(User $user, Attendance $attendance): bool
    {
        // الطالب قد يكون خارج نطاق رؤية المستخدم (CenterScope) فتُرجع العلاقة null —
        // بدون الفحص الآمن ينهار التفويض بخطأ 500 بدل رفض نظيف 403
        $studentCircleId = $attendance->student?->circle_id;

        if (!$studentCircleId) return false;

        return $this->access->canAccessCircle($user, $studentCircleId);
    }
}
