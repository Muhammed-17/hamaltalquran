<?php

namespace App\Models\Scopes;

use App\Models\Teacher;
use App\Models\User;
use App\Services\UserAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * ✅ CenterScope — مسؤول فقط عن تحديد الدور الحالي وبناء where clauses.
 * كل منطق الصلاحيات وتفاصيل الفلترة أصبح داخل UserAccessService.
 */
class CenterScope implements Scope
{
    private const IGNORED_TABLES = [
        'centers',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
    ];

    public function apply(Builder $builder, Model $model): void
    {
        if (in_array($model->getTable(), self::IGNORED_TABLES)) return;

        $user = auth()->user();
        if (!$user) return;

        if ($user->hasRole(['admin', 'general_manager'])) return;
        if ($user->hasRole('guardian')) return;

        $access  = app(UserAccessService::class);
        $teacher = $access->teacher($user);

        if (!$teacher) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $table = $model->getTable();

        $builder->where(function ($nestedQuery) use ($table, $teacher, $user, $access) {
            $nestedQuery->where(function ($q) use ($table, $teacher, $user, $access) {
                // Refactored: تمرير User لـ applyManagerScope + تمرير Teacher جاهز لـ applyTeacherScope
                match (true) {
                    $user->hasRole('manager')    => $this->applyManagerScope($q, $table, $teacher, $user, $access),
                    $user->hasRole('teacher')    => $this->applyTeacherScope($q, $table, $user, $teacher, $access),
                    $user->hasRole('supervisor') => $this->applySupervisorTeachersScope($q, $table, $teacher, $access),   // ← أضف $access هنا
                    default                      => $q->whereRaw('1 = 0'),
                };
            });

            $circleIds = $access->supervisorCircleIds($user);
            if (!$circleIds->isEmpty()) {
                $nestedQuery->orWhere(function ($q) use ($table, $circleIds, $access) {
                    // Refactored: استدعاء عبر السيرفس بدل الدالة المحلية
                    $access->applyScopeByCircleIds($q, $table, $circleIds);
                });
            }
        });
    }

    // Refactored: تصحيح كامل — استقبال User وتمريره لـ managerCircleIds()، وتطبيق الفلترة فعليًا
    private function applyManagerScope(Builder $builder, string $table, Teacher $teacher, User $user, UserAccessService $access): void
    {
        $centerId = $access->teacherCenterId($teacher);   // ← جديد

        if (is_null($centerId)) {
            $builder->whereRaw('1 = 0');
            return;
        }

        if ($table === 'teachers' || $table === 'educational_lessons') {
            $builder->whereHas('branch', fn($q) => $q->where('center_id', $centerId)); 
            return;
        }

        $managerCircleIds = $access->managerCircleIds($user);

        if ($managerCircleIds->isEmpty()) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $access->applyScopeByCircleIds($builder, $table, $managerCircleIds);
    }

    private function applySupervisorTeachersScope(Builder $builder, string $table, Teacher $teacher, UserAccessService $access): void   // ← لاحظ إضافة $access كباراميتر
    {
        $centerId = $access->teacherCenterId($teacher);   // ← جديد

        if (is_null($centerId)) {
            $builder->whereRaw('1 = 0');
            return;
        }

        if ($table === 'teachers' || $table === 'educational_lessons') {
            $builder->whereHas('branch', fn($q) => $q->where('center_id', $centerId));   
            return;
        }

        $builder->whereRaw('1 = 0');
    }

    private function applyTeacherScope(Builder $builder, string $table, User $user, Teacher $teacher, UserAccessService $access): void
    {
        if ($table === 'teachers' || $table === 'educational_lessons') {
            $centerId = $access->teacherCenterId($teacher);

            if (is_null($centerId)) {
                $builder->whereRaw('1 = 0');
                return;
            }
            $builder->whereHas('branch', fn($q) => $q->where('center_id', $centerId));   
            return;
        }
        $circleIds = $access->teacherCircleIds($user);

        if ($circleIds->isEmpty()) {
            $builder->whereRaw('1 = 0');
            return;
        }

        if ($table === 'subscriptions') {
            $builder->whereIn('circle_id', $circleIds);
            return;
        }

        $access->applyScopeByCircleIds($builder, $table, $circleIds);
    }
    // Refactored: حُذفت applyScopeByCircleIds المحلية بالكامل — انتقلت لـ UserAccessService

    public static function clearCache(): void
    {
        app(UserAccessService::class)->clearCache();
    }
}
