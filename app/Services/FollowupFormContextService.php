<?php

namespace App\Services;

use App\Models\Circle;
use App\Models\Teacher;
use App\Models\Surah;
use App\Models\StudentWeeklyFollowup;
use App\Models\Scopes\CenterScope;
use Illuminate\Support\Facades\Auth;

/**
 * Class FollowupFormContextService
 *
 * يبني بيانات السياق المشتركة اللي تحتاجها فورمات المتابعة الأسبوعية
 * (الحلقات المتاحة، المعلمين، السور، وقواعد إخفاء/افتراض الحلقة للمعلم الفردي)
 * بدل تكرار نفس المنطق في createGroup/editGroup/showGroup و
 * createIndividual/editIndividual/showIndividual داخل الـ Controller.
 */
class FollowupFormContextService
{
    public function __construct(
        private UserAccessService $access
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // Group Context
    // ═══════════════════════════════════════════════════════════════

    /**
     * السياق المطلوب لفورم المتابعة الجماعية (Create/Edit/Show).
     */
    public function forGroup(): array
    {
        $accessibleCircleIds  = $this->getAccessibleCircles('group');
        $accessibleTeacherIds = $this->getAccessibleTeachers();

        $circles = Circle::whereIn('id', $accessibleCircleIds)
            ->where('type', 'group')
            ->get();

        $teachers = Teacher::with('user')
            ->whereIn('id', $accessibleTeacherIds)
            ->get();

        $surahs = Surah::orderBy('id')->get();

        return compact('circles', 'teachers', 'surahs');
    }

    // ═══════════════════════════════════════════════════════════════
    // Individual Context
    // ═══════════════════════════════════════════════════════════════

    /**
     * السياق المطلوب لفورم المتابعة الفردية (Create/Edit/Show).
     *
     * @param  StudentWeeklyFollowup|null $excludeFollowup  المتابعة الحالية (في وضع Edit) لاستبعادها من excludedStudentIds
     * @param  string|null $weekStart  بداية الأسبوع لحساب الطلاب المستبعدين (لهم متابعة فردية بالفعل هذا الأسبوع)
     */
    public function forIndividual(?StudentWeeklyFollowup $excludeFollowup = null, ?string $weekStart = null): array
    {
        $user = Auth::user();
        $isPlainTeacher = !$user->hasRole(['admin', 'general_manager', 'manager', 'supervisor']);

        $accessibleTeacherIds = $this->getAccessibleTeachers();
        $accessibleCircleIds  = $this->getAccessibleCircles('individual');

        $circles = Circle::whereIn('id', $accessibleCircleIds)
            ->where('type', 'individual')
            ->with(['students' => function ($query) {
                $query->withoutGlobalScope(CenterScope::class);
            }])
            ->get();

        $teacher = $this->access->teacher($user);
        $teacherCircleIds = $teacher ? $this->access->teacherCircleIdsWithinCenter($user) : collect();
        $teacherCircles = $teacher
            ? Circle::whereIn('id', $teacherCircleIds)->where('type', 'individual')->get()
            : collect();

        $hideCircleField = $isPlainTeacher && $teacherCircles->count() === 1;
        $defaultCircleId = $hideCircleField ? $teacherCircles->first()->id : null;

        $teachers = Teacher::with('user')
            ->whereIn('id', $accessibleTeacherIds)
            ->get();

        $surahs = Surah::orderBy('id')->get();

        $excludedStudentIds = $weekStart
            ? $this->excludedIndividualStudentIds($weekStart, $excludeFollowup)
            : [];

        return compact(
            'circles',
            'teachers',
            'surahs',
            'hideCircleField',
            'defaultCircleId',
            'excludedStudentIds'
        );
    }

    /**
     * يجيب الطلاب اللي عندهم متابعة فردية بالفعل في نفس الأسبوع
     * (يُستبعدون من قائمة الاختيار في الفورم).
     */
    private function excludedIndividualStudentIds(string $weekStart, ?StudentWeeklyFollowup $excludeFollowup = null): array
    {
        return StudentWeeklyFollowup::where('plan_type', 'individual')
            ->whereDate('week_start', $weekStart)
            ->when($excludeFollowup, fn($q) => $q->where('id', '!=', $excludeFollowup->id))
            ->whereNotNull('student_id')
            ->pluck('student_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->values()
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════════
    // Access Helpers (منقولة من الـ Controller)
    // ═══════════════════════════════════════════════════════════════

    private function getAccessibleCircles(string $type): array
    {
        $user = Auth::user();

        if ($user->hasRole(['admin', 'general_manager'])) {
            return Circle::where('type', $type)->pluck('id')->toArray();
        }

        if ($user->hasRole('manager')) {
            $circleIds = $this->access->managerCircleIds($user);
        } elseif ($user->hasRole('supervisor')) {
            $circleIds = $this->access->supervisorCircleIds($user);
        } else {
            $circleIds = $this->access->teacherCircleIdsWithinCenter($user);
        }

        return Circle::where('type', $type)
            ->whereIn('id', $circleIds)
            ->pluck('id')
            ->toArray();
    }

    private function getAccessibleTeachers(): array
    {
        $user = Auth::user();

        if ($user->hasRole(['admin', 'general_manager', 'manager', 'supervisor'])) {
            return $this->access->accessibleTeachers($user)->pluck('id')->toArray();
        }

        $teacher = $this->access->teacher($user);
        return $teacher ? [$teacher->id] : [];
    }

    public function resolveCurrentCenterId(): ?int
    {
        $user = Auth::user();
        $teacher = $this->access->teacher($user);

        if ($teacher?->center_id) {
            return $teacher->center_id;
        }

        if ($user->center_id) {
            return $user->center_id;
        }

        $firstAccessibleCircleId = collect($this->getAccessibleCircles('individual'))
            ->first() ?? collect($this->getAccessibleCircles('group'))->first();

        if ($firstAccessibleCircleId) {
            return Circle::find($firstAccessibleCircleId)?->center_id;
        }

        return null;
    }
}