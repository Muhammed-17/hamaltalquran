<?php

namespace App\Policies;

use App\Models\Circle;
use App\Models\User;
use App\Services\UserAccessService;

class CirclePolicy
{
    public function __construct(protected UserAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher']);
    }

    public function view(User $user, Circle $circle): bool
    {
        if (!$user->hasRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher'])) return false;
        return $this->canAccessCircle($user, $circle);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Circle $circle): bool
    {
        if (!$user->can('edit circles')) return false;
        return $this->canAccessCircle($user, $circle);
    }

    public function delete(User $user, Circle $circle): bool
    {
        return $user->hasRole('admin');
    }

    private function canAccessCircle(User $user, Circle $circle): bool
    {
        if ($user->hasRole(['admin', 'general_manager'])) return true;

        $teacher = $this->access->teacher($user);
        if (!$teacher) return false;

        // ✅ المشرف النقي — فقط الحلقات التي يشرف عليها (منطق خاص محتفظ به عمدًا،
        // يفحص pivot role=supervisor على هذه الحلقة تحديدًا، بخلاف
        // UserAccessService::canAccessCircle العامة — انظر التحليل السابق)
        if ($user->hasRole('supervisor') && !$user->hasRole(['manager', 'teacher', 'admin', 'general_manager'])) {
            return $circle->supervisors()->where('teachers.id', $teacher->id)->exists();
        }

        if ($user->hasRole('manager')) {
            // مركز المعلم يُشتق من فرعه (لا يوجد عمود center_id على المعلمين)
            $centerId = $teacher->branch?->center_id;

            return $centerId !== null && $circle->center_id === $centerId;
        }

        // ✅ المعلم — حلقاته (main/assistant) + مشرف على هذه الحلقة تحديدًا
        if ($user->hasRole('teacher')) {
            // Refactored: استخدام علاقة Eloquent بدلاً من DB::table (وإصلاح باگ DB غير مستورد أصلاً)
            $isMainOrAssistant = $teacher->circles()
                ->wherePivot('circle_id', $circle->id)
                ->wherePivotIn('role', ['main', 'assistant'])
                ->exists();

            $isSupervisor = $circle->supervisors()->where('teachers.id', $teacher->id)->exists();

            return $isMainOrAssistant || $isSupervisor;
        }

        return false;
    }
}