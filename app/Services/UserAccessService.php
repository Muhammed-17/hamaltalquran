<?php

namespace App\Services;

use App\Models\Center;
use App\Models\Circle;
use App\Models\Scopes\CenterScope;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * ✅ UserAccessService — المصدر الوحيد لكل منطق الصلاحيات والوصول.
 */
class UserAccessService
{
    private array $teacherCache = [];
    private array $circleIdsCache = [];

    public function teacher(User $user): ?Teacher
    {
        if (!array_key_exists($user->id, $this->teacherCache)) {
            $this->teacherCache[$user->id] = Teacher::withoutGlobalScope(CenterScope::class)
                ->where('user_id', $user->id)
                ->first();
        }

        return $this->teacherCache[$user->id];
    }


    public function teacherCenterId(?Teacher $teacher): ?int
    {
        return $teacher?->branch?->center_id;
    }

    public function accessibleBranches(User $user): Builder
    {
        if ($user->hasRole(['admin', 'general_manager'])) {
            return \App\Models\Branch::orderBy('name');
        }

        $teacher = $this->teacher($user);
        $centerId = $this->teacherCenterId($teacher);

        if ($centerId) {
            return \App\Models\Branch::where('center_id', $centerId)->orderBy('name');
        }

        return \App\Models\Branch::whereRaw('1=0');
    }

    private function rememberCircleIds(string $cacheKey, \Closure $resolver): Collection
    {
        if (!isset($this->circleIdsCache[$cacheKey])) {
            $this->circleIdsCache[$cacheKey] = $resolver();
        }

        return $this->circleIdsCache[$cacheKey];
    }

    public function teacherCircleIds(User $user): Collection
    {
        $teacher = $this->teacher($user);
        if (!$teacher) {
            return collect();
        }

        return $this->rememberCircleIds(
            "teacher_{$teacher->id}",
            fn() => $teacher->circles()
                ->withoutGlobalScope(CenterScope::class)
                ->wherePivotIn('role', ['main', 'assistant'])
                ->pluck('circles.id')
        );
    }

    // بعد (رجّعتها لمنطقها الأصلي: حلقات المعلم نفسه ضمن مركزه)
    public function teacherCircleIdsWithinCenter(User $user): Collection
    {
        $teacher = $this->teacher($user);
        $centerId = $this->teacherCenterId($teacher);
        if (!$teacher || !$centerId) {
            return collect();
        }

        return $this->rememberCircleIds(
            "teacher_center_{$teacher->id}",
            fn() => $teacher->circles()
                ->withoutGlobalScope(CenterScope::class)
                ->wherePivotIn('role', ['main', 'assistant'])
                ->whereHas('branch', fn($q) => $q->where('center_id', $centerId))
                ->pluck('circles.id')
        );
    }

    /**
     * الحلقات التابعة للفروع التي يشرف عليها هذا المعلم (عبر branch_teacher).
     */
    public function supervisorCircleIds(User $user): Collection
    {
        $teacher = $this->teacher($user);
        if (!$teacher) {
            return collect();
        }

        return $this->rememberCircleIds(
            "supervisor_{$teacher->id}",
            fn() => Circle::withoutGlobalScope(CenterScope::class)
                ->whereHas('branch.supervisors', function ($q) use ($teacher) {
                    $q->withoutGlobalScope(CenterScope::class)   // ← الإضافة الحاسمة
                        ->where('teachers.id', $teacher->id);
                })
                ->pluck('id')
        );
    }

    // بعد
    public function managerCircleIds(User $user): Collection
    {
        $teacher = $this->teacher($user);
        $centerId = $this->teacherCenterId($teacher);
        if (!$teacher || !$centerId) {
            return collect();
        }

        return $this->rememberCircleIds(
            "manager_{$centerId}",
            fn() => Circle::withoutGlobalScope(CenterScope::class)
                ->whereHas('branch', fn($q) => $q->where('center_id', $centerId))
                ->pluck('id')
        );
    }

    /**
     * أنواع الحلقات (group/individual) التي يرتبط بها المستخدم كمعلم
     * (أساسي/مساعد) أو كمشرف. تُستخدم للتحكم في ظهور روابط المتابعة بالـ sidebar.
     */
    public function teacherCircleTypes(User $user): Collection
    {
        $teacher = $this->teacher($user);
        if (!$teacher) {
            return collect();
        }

        return $this->rememberCircleIds(
            "circle_types_{$teacher->id}",
            fn() => $teacher->circles()
                ->withoutGlobalScope(CenterScope::class)
                ->wherePivotIn('role', ['main', 'assistant', 'supervisor'])
                ->distinct()
                ->pluck('circles.type')
        );
    }

    public function accessibleCircles(User $user): Builder
    {
        if ($user->hasRole(['admin', 'general_manager'])) {
            return Circle::withoutGlobalScope(CenterScope::class)->orderBy('name');
        }

        if ($user->hasRole('guardian')) {
            return Circle::withoutGlobalScope(CenterScope::class)->whereIn(
                'id',
                Student::where('guardian_id', $user->id)
                    ->whereNotNull('circle_id')
                    ->where('status', 'مقيد')
                    ->pluck('circle_id')
            )->orderBy('name');
        }

        $teacher = $this->teacher($user);
        $centerId = $this->teacherCenterId($teacher);
        if (!$teacher || !$centerId) {
            return Circle::withoutGlobalScope(CenterScope::class)->whereRaw('1=0');
        }

        if ($user->hasRole('manager')) {
            return Circle::withoutGlobalScope(CenterScope::class)
                ->whereHas('branch', fn($q) => $q->where('center_id', $centerId))
                ->orderBy('name');
        }

        if ($user->hasRole('supervisor')) {
            $circleIds = $this->supervisorCircleIds($user);

            return $circleIds->isEmpty()
                ? Circle::withoutGlobalScope(CenterScope::class)->whereRaw('1=0')
                : Circle::withoutGlobalScope(CenterScope::class)->whereIn('id', $circleIds)->orderBy('name');
        }

        $circleIds = $this->teacherCircleIds($user);

        return $circleIds->isEmpty()
            ? Circle::withoutGlobalScope(CenterScope::class)->whereRaw('1=0')
            : Circle::withoutGlobalScope(CenterScope::class)->whereIn('id', $circleIds)->orderBy('name');
    }

    public function accessibleCenters(User $user): Builder
    {
        if ($user->hasRole(['admin', 'general_manager'])) {
            return Center::orderBy('name');
        }

        $teacher = $this->teacher($user);
        $centerId = $this->teacherCenterId($teacher);

        if ($centerId) {
            return Center::where('id', $centerId);
        }

        return Center::whereRaw('1=0');
    }

    /**
     * Teachers/supervisors visible to the given user, scoped by role:
     * - admin / general_manager: everyone, across all branches.
     * - supervisor: only staff assigned to the circles this user supervises.
     * - manager (and any other center-bound role): everyone within the same branch/center.
     */
    public function accessibleTeachers(User $user): Builder
    {
        if ($user->hasRole(['admin', 'general_manager'])) {
            return Teacher::withoutGlobalScope(CenterScope::class)
                ->with('user.roles')
                ->whereHas('user', fn($u) => $u->where('status', 'active'));
        }

        if ($user->hasRole('supervisor')) {
            $circleIds = $this->supervisorCircleIds($user);

            $query = Teacher::withoutGlobalScope(CenterScope::class)
                ->with('user.roles')
                ->whereHas('user', fn($u) => $u->where('status', 'active'));

            return $circleIds->isEmpty()
                ? $query->whereRaw('1=0')
                : $query->whereHas('circles', fn($cq) => $cq
                    ->withoutGlobalScope(CenterScope::class)
                    ->whereIn('circles.id', $circleIds));
        }

        $teacher = $this->teacher($user);
        $centerId = $this->teacherCenterId($teacher);

        if ($teacher && $centerId) {
            $query = Teacher::withoutGlobalScope(CenterScope::class)
                ->with('user.roles')
                ->whereHas('user', fn($u) => $u->where('status', 'active'));

            $this->applyTeacherCenterFilter($query, $centerId);

            return $query;
        }

        return Teacher::whereRaw('1=0');
    }

    public function accessibleSupervisors(User $user): Builder
    {
        return $this->accessibleTeachers($user)
            ->whereHas('user.roles', fn($r) => $r->whereIn('name', ['supervisor', 'manager', 'general_manager']));
    }

    public function applyTeacherCenterFilter(Builder $query, int $centerId): void
    {
        $query->where(function ($q) use ($centerId) {
            $q->whereHas('branch', fn($bq) => $bq->where('center_id', $centerId))
                ->orWhereHas('circles', function ($cq) use ($centerId) {
                    $cq->withoutGlobalScope(CenterScope::class)
                        ->whereHas('branch', fn($bq) => $bq->where('center_id', $centerId));
                });
        });
    }

    public function canAccessCircle(User $user, int $circleId): bool
    {
        return $this->accessibleCircles($user)->whereKey($circleId)->exists();
    }

    public function canAccessStudent(User $user, Student $student): bool
    {
        if ($user->hasRole(['admin', 'general_manager'])) {
            return true;
        }

        if ($user->hasRole('guardian')) {
            return (int) $student->guardian_id === (int) $user->id;
        }

        if (!$student->circle_id) {
            return false;
        }

        return $this->canAccessCircle($user, (int) $student->circle_id);
    }


    public function applyScopeByCircleIds(Builder $builder, string $table, Collection|array $circleIds): void
    {
        match ($table) {
            'circles'                    => $builder->whereIn('id', $circleIds),
            'students',
            'subscriptions',
            'surah_tests'                 => $builder->whereIn('circle_id', $circleIds),
            'attendances'                => $builder->whereIn('student_id', function ($sub) use ($circleIds) {
                $sub->select('id')->from('students')->whereIn('circle_id', $circleIds);
            }),
            'student_construction_details' => $builder->whereIn('student_id', function ($sub) use ($circleIds) {
                $sub->select('id')->from('students')->whereIn('circle_id', $circleIds);
            }),
            'student_activities',
            'other_assessments',
            'recommendations',
            'student_weekly_disciplines',
            'student_weekly_new_memorizations',
            'student_weekly_old_memorizations',
            'student_weekly_revisions',
            'student_weekly_tajweed_assessments',
            'student_weekly_foundation_levels',
            'student_weekly_educational_lessons' => $builder->whereIn('weekly_followup_id', function ($sub) use ($circleIds) {
                $sub->select('id')
                    ->from('student_weekly_followups')
                    ->where(function ($q) use ($circleIds) {
                        $q->whereIn('circle_id', $circleIds)
                            ->orWhereIn('student_id', function ($sub2) use ($circleIds) {
                                $sub2->select('id')->from('students')->whereIn('circle_id', $circleIds);
                            });
                    });
            }),

            'student_weekly_followups' => $builder->where(function ($q) use ($circleIds) {
                $q->whereIn('circle_id', $circleIds)
                    ->orWhereIn('student_id', function ($sub) use ($circleIds) {
                        $sub->select('id')->from('students')->whereIn('circle_id', $circleIds);
                    });
            }),

            'group_session_plans' => $builder->whereIn('circle_id', $circleIds),

            'behavioral_notes'           => $builder->where(function ($q) use ($circleIds) {
                $q->whereIn('circle_id', $circleIds)
                    ->orWhereIn('student_id', function ($sub) use ($circleIds) {
                        $sub->select('id')->from('students')->whereIn('circle_id', $circleIds);
                    });
            }),

            // educational_lessons: بدون circle_id (فيها center_id فقط) — تُفلتر من CenterScope مباشرة، ليس من هنا
            'educational_lessons' => null,

            // teachers: تصل هنا فقط عبر فرع "orWhere" الخاص بـ supervisorCircleIds في CenterScope
            // (الفرع الأساسي لـ teachers متعامل معه بشرط خاص في applyManagerScope/applyTeacherScope/applySupervisorTeachersScope)
            'teachers' => $builder->whereHas('circles', fn($cq) => $cq
                ->withoutGlobalScope(CenterScope::class)
                ->whereIn('circles.id', $circleIds)),
            default => throw new \InvalidArgumentException(
                "applyScopeByCircleIds: جدول غير معروف/غير معالج: [{$table}] — أضف case له في UserAccessService."
            ),
        };
    }

    public function clearCache(): void
    {
        $this->teacherCache   = [];
        $this->circleIdsCache = [];
    }
}
