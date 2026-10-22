<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentWeeklyFollowup;
use App\Models\StudentActivity;
use App\Models\Scopes\CenterScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WeeklyFollowupService
{
    public function __construct(
        private RecommendationService $recommendationService
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // Batch Operations (Group)
    // ═══════════════════════════════════════════════════════════════

    public function createBatch(array $planData, array $studentsData, array $activitiesData = []): string
    {
        $batchId = (string) Str::uuid();

        $createdFollowups = collect();

        foreach ($studentsData as $studentData) {
            $followup = $this->createFollowupWithRelations($batchId, $planData, $studentData);
            $createdFollowups->push($followup);
        }

        $this->syncActivitiesForBatch($batchId, $activitiesData);

        // ⬅️ استدعاء واحد بعد كل الطلاب بدل استدعاء لكل طالب
        $this->recommendationService->generateAndStoreBatch($createdFollowups);

        return $batchId;
    }

    public function updateBatch(string $batchId, array $planData, array $studentsData, array $activitiesData = []): void
    {
        $existingFollowups = StudentWeeklyFollowup::byBatch($batchId)
            ->with(['newMemorizations', 'revisions', 'oldMemorizations'])
            ->get()
            ->keyBy('student_id');

        $submittedStudentIds = collect($studentsData)->pluck('student_id')->toArray();

        foreach ($existingFollowups as $studentId => $followup) {
            if (!in_array($studentId, $submittedStudentIds)) {
                $this->deleteFollowupWithRelations($followup);
                $existingFollowups->forget($studentId);
            }
        }

        $affectedFollowups = collect();

        foreach ($studentsData as $studentData) {
            $studentId = $studentData['student_id'];

            if ($existingFollowups->has($studentId)) {
                $followup = $this->updateFollowupWithRelations($existingFollowups[$studentId], $planData, $studentData);
            } else {
                $followup = $this->createFollowupWithRelations($batchId, $planData, $studentData);
            }

            $affectedFollowups->push($followup);
        }

        $this->syncActivitiesForBatch($batchId, $activitiesData);

        // ⬅️ استدعاء واحد بدل استدعاء لكل طالب
        $this->recommendationService->generateAndStoreBatch($affectedFollowups);
    }

    public function deleteBatch(string $batchId): void
    {
        $followups = StudentWeeklyFollowup::byBatch($batchId)->get();

        foreach ($followups as $followup) {
            $this->deleteFollowupWithRelations($followup);
        }

        StudentActivity::where('follow_id', $batchId)->delete();
    }

    // ═══════════════════════════════════════════════════════════════
    // Followup CRUD (Group)
    // ═══════════════════════════════════════════════════════════════

    private function createFollowupWithRelations(string $batchId, array $planData, array $studentData): StudentWeeklyFollowup
    {
        $followup = StudentWeeklyFollowup::create([
            'batch_id'   => $batchId,
            'plan_type'  => 'group',
            'center_id'  => $planData['center_id'] ?? null,
            'circle_id'  => $planData['circle_id'],
            'student_id' => $studentData['student_id'],
            'teacher_id' => $planData['teacher_id'],
            'week_start' => $planData['week_start'],
            'week_end'   => $planData['week_end'],
            'study_days' => $planData['study_days'] ?? [],
            'notes'      => $studentData['general_notes'] ?? null,
        ]);

        $this->syncMemorization($followup, 'newMemorizations', $planData['new_memorization'] ?? [], [
            'average_level' => $studentData['new_memorization_level'] ?? null,
        ]);
        $this->syncMemorization($followup, 'revisions', $planData['revision'] ?? [], [
            'average_level' => $studentData['revision_level'] ?? null,
        ]);
        $this->syncMemorization($followup, 'oldMemorizations', $planData['old_memorization'] ?? [], [
            'average_level' => $studentData['old_memorization_level'] ?? null,
        ]);

        $this->syncSimpleAssessment($followup, 'discipline', $studentData['discipline_level'] ?? null, $planData['discipline_achievement'] ?? null);
        $this->syncSimpleAssessment($followup, 'tajweedAssessment', $studentData['tajweed_level'] ?? null, $planData['tajweed_achievement'] ?? null);
        $this->syncSimpleAssessment($followup, 'foundationLevel', $studentData['foundation_level_level'] ?? null, $planData['foundation_level_achievement'] ?? null);

        $this->syncEducationalLesson($followup, $planData['educational_lesson_id'] ?? null, $planData, $studentData);
        // ⬅️ توليد وتخزين التوصية لهذا الطالب فور إنشاء متابعته

        return $followup;
    }

    private function updateFollowupWithRelations(StudentWeeklyFollowup $followup, array $planData, array $studentData): StudentWeeklyFollowup
    {
        $followup->update([
            'circle_id'  => $planData['circle_id'],
            'teacher_id' => $planData['teacher_id'],
            'week_start' => $planData['week_start'],
            'week_end'   => $planData['week_end'],
            'study_days' => $planData['study_days'] ?? [],
            'notes'      => $studentData['general_notes'] ?? null,
        ]);

        $this->syncMemorization($followup, 'newMemorizations', $planData['new_memorization'] ?? [], [
            'average_level' => $studentData['new_memorization_level'] ?? null,
        ]);
        $this->syncMemorization($followup, 'revisions', $planData['revision'] ?? [], [
            'average_level' => $studentData['revision_level'] ?? null,
        ]);
        $this->syncMemorization($followup, 'oldMemorizations', $planData['old_memorization'] ?? [], [
            'average_level' => $studentData['old_memorization_level'] ?? null,
        ]);

        $this->syncSimpleAssessment($followup, 'discipline', $studentData['discipline_level'] ?? null, $planData['discipline_achievement'] ?? null);
        $this->syncSimpleAssessment($followup, 'tajweedAssessment', $studentData['tajweed_level'] ?? null, $planData['tajweed_achievement'] ?? null);
        $this->syncSimpleAssessment($followup, 'foundationLevel', $studentData['foundation_level_level'] ?? null, $planData['foundation_level_achievement'] ?? null);

        $this->syncEducationalLesson($followup, $planData['educational_lesson_id'] ?? null, $planData, $studentData);
        // ⬅️ إعادة توليد وتخزين التوصية بعد أي تحديث لبيانات الطالب
        return $followup;
    }

    // public function deleteFollowupWithRelations(StudentWeeklyFollowup $followup): void
    // {
    //     $followup->newMemorizations()->delete();
    //     $followup->revisions()->delete();
    //     $followup->oldMemorizations()->delete();
    //     $followup->discipline()->delete();
    //     $followup->tajweedAssessment()->delete();
    //     $followup->foundationLevel()->delete();
    //     $followup->educationalLessonAssessment()->delete();
    //     $followup->activities()->delete();
    //     $followup->recommendation()->delete();
    //     $followup->delete();
    // }
    public function deleteFollowupWithRelations(StudentWeeklyFollowup $followup): void
    {
        $followup->delete(); // الـ cascade هيحذف كل العلاقات التسعة تلقائيًا
    }

    // ═══════════════════════════════════════════════════════════════
    // Activities
    // ═══════════════════════════════════════════════════════════════

    public function syncActivitiesForBatch(string $batchId, array $activitiesData): void
    {
        StudentActivity::where('follow_id', $batchId)->delete();

        $followupIds = StudentWeeklyFollowup::byBatch($batchId)->pluck('id');

        if ($followupIds->isEmpty()) {
            return;
        }

        $validActivities = $this->filterValidActivities($activitiesData);

        if (empty($validActivities)) {
            return;
        }

        $rows = [];
        $now = now();

        foreach ($validActivities as $activity) {
            foreach ($followupIds as $followupId) {
                $rows[] = [
                    'weekly_followup_id' => $followupId,
                    'follow_id'          => $batchId,
                    'activity_type'      => $activity['activity_type'],
                    'activity_name'      => $activity['activity_name'],
                    'activity_date'      => $activity['activity_date'],
                    'notes'              => $activity['notes'] ?? null,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }
        }

        // إدراج كل الصفوف بستعلام واحد بدل O(n×m) استعلام
        // ملاحظة: لو عدد الصفوف كبير جدًا (آلاف)، استخدم chunk لتفادي حد MySQL max_allowed_packet
        collect($rows)->chunk(500)->each(function ($chunk) {
            StudentActivity::insert($chunk->toArray());
        });
    }

    /**
     * فلترة الأنشطة الصالحة فقط (تستبعد المحذوف والناقص).
     */
    private function filterValidActivities(array $activitiesData): array
    {
        return array_values(array_filter($activitiesData, function ($activity) {
            if (!empty($activity['_deleted']) && $activity['_deleted'] !== 'false') {
                return false;
            }

            return !empty($activity['activity_type'])
                && !empty($activity['activity_name'])
                && !empty($activity['activity_date']);
        }));
    }
    /**
     * Sync activities for an individual follow-up.
     */
    public function syncActivitiesForIndividual(int $followupId, array $activitiesData): void
    {
        StudentActivity::where('weekly_followup_id', $followupId)->delete();

        foreach ($activitiesData as $activity) {
            if (!empty($activity['_deleted']) && $activity['_deleted'] !== 'false') {
                continue;
            }

            if (empty($activity['activity_type']) || empty($activity['activity_name']) || empty($activity['activity_date'])) {
                continue;
            }

            StudentActivity::create([
                'weekly_followup_id' => $followupId,
                'follow_id'          => null,
                'activity_type'      => $activity['activity_type'],
                'activity_name'      => $activity['activity_name'],
                'activity_date'      => $activity['activity_date'],
                'notes'              => $activity['notes'] ?? null,
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Educational Lesson
    // ═══════════════════════════════════════════════════════════════

    private function syncEducationalLesson(StudentWeeklyFollowup $followup, ?int $educationalLessonId, array $planData, array $studentData): void
    {
        if (!$educationalLessonId) {
            $followup->educationalLessonAssessment()->delete();
            return;
        }

        $data = [
            'educational_lesson_id' => $educationalLessonId,
            'level'                 => $studentData['educational_lesson_level'] ?? null,
            'notes'                 => $planData['educational_lesson_achievement']
                ?? $studentData['educational_lesson_notes']
                ?? null,
        ];

        $existing = $followup->educationalLessonAssessment;

        if ($existing) {
            $existing->update($data);
        } else {
            $followup->educationalLessonAssessment()->create($data);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Memorization Sync
    // ═══════════════════════════════════════════════════════════════

    private function syncMemorization(
        StudentWeeklyFollowup $followup,
        string $relation,
        array $planData,
        array $studentData
    ): void {
        $merged = $this->mergePlanAndStudentData($planData, $studentData);

        if (!$this->hasValidRange($merged)) {
            $followup->{$relation}()->delete();
            return;
        }

        $existing = $followup->{$relation}()->first();

        $data = [
            'plan_from_surah_id'  => $merged['from_surah_id'],
            'plan_from_ayah'      => $merged['from_ayah'],
            'plan_to_surah_id'    => $merged['to_surah_id'],
            'plan_to_ayah'        => $merged['to_ayah'],
            'plan_comparison'     => $merged['plan_comparison'] ?? null,
            'progress_difference' => $merged['progress_difference'] ?? null,
            'average_level'       => $studentData['average_level'] ?? null,
            'notes'               => $merged['notes'] ?? null,
        ];

        if ($existing) {
            $existing->update($data);
        } else {
            $followup->{$relation}()->create($data);
        }
    }

    private function mergePlanAndStudentData(array $planData, array $studentData): array
    {
        return [
            'from_surah_id'       => $planData['from_surah_id'] ?? $studentData['from_surah_id'] ?? null,
            'from_ayah'           => $planData['from_ayah'] ?? $studentData['from_ayah'] ?? null,
            'to_surah_id'         => $planData['to_surah_id'] ?? $studentData['to_surah_id'] ?? null,
            'to_ayah'             => $planData['to_ayah'] ?? $studentData['to_ayah'] ?? null,
            'plan_comparison'     => $planData['plan_comparison'] ?? $studentData['plan_comparison'] ?? null,
            'progress_difference' => $planData['progress_difference'] ?? $studentData['progress_difference'] ?? null,
            'notes'               => $planData['notes'] ?? $studentData['notes'] ?? null,
        ];
    }

    private function hasValidRange(array $data): bool
    {
        return !empty($data['from_surah_id'])
            && !empty($data['to_surah_id'])
            && !empty($data['from_ayah'])
            && !empty($data['to_ayah']);
    }

    // ═══════════════════════════════════════════════════════════════
    // Simple Assessment
    // ═══════════════════════════════════════════════════════════════

    private function syncSimpleAssessment(StudentWeeklyFollowup $followup, string $relation, ?string $level, ?string $notes): void
    {
        $existing = $followup->{$relation}()->first();

        if (empty($level)) {
            if ($existing) {
                $existing->delete();
            }
            return;
        }

        $data = [
            'level' => $level,
            'notes' => $notes,
        ];

        if ($existing) {
            $existing->update($data);
        } else {
            $followup->{$relation}()->create($data);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Validation Helpers
    // ═══════════════════════════════════════════════════════════════

    public function getDuplicateStudents(array $studentIds, string $weekStart, ?string $excludeBatchId = null): Collection
    {
        $query = StudentWeeklyFollowup::whereIn('student_id', $studentIds)
            ->where('week_start', $weekStart);

        if ($excludeBatchId) {
            $query->where('batch_id', '!=', $excludeBatchId);
        }

        return $query->with('student')->get()->pluck('student.name', 'student_id');
    }

    public function validateStudentsInCircle(array $studentIds, int $circleId): array
    {
        $validStudentIds = Student::withoutGlobalScope(CenterScope::class)
            ->where('circle_id', $circleId)
            ->whereIn('id', $studentIds)
            ->pluck('id')
            ->toArray();

        return array_diff($studentIds, $validStudentIds);
    }

    // ═══════════════════════════════════════════════════════════════
    // Individual Followup Operations
    // ═══════════════════════════════════════════════════════════════

    public function createIndividualFollowup(array $planData, array $studentData): StudentWeeklyFollowup
    {
        $followup = StudentWeeklyFollowup::create([
            'plan_type'  => 'individual',
            'center_id'  => $planData['center_id'] ?? null,
            'circle_id'  => $planData['circle_id'],
            'student_id' => $studentData['student_id'],
            'teacher_id' => $planData['teacher_id'],
            'week_start' => $planData['week_start'],
            'week_end'   => $planData['week_end'],
            'study_days' => $planData['study_days'] ?? [],
            'notes'      => $studentData['general_notes'] ?? null,
        ]);

        $this->syncIndividualRelations($followup, $planData, $studentData);

        return $followup;
    }

    public function updateIndividualFollowup(StudentWeeklyFollowup $followup, array $planData, array $studentData): void
    {
        $followup->update([
            'circle_id'  => $planData['circle_id'],
            'student_id' => $studentData['student_id'],
            'teacher_id' => $planData['teacher_id'],
            'week_start' => $planData['week_start'],
            'week_end'   => $planData['week_end'],
            'study_days' => $planData['study_days'] ?? [],
            'notes'      => $studentData['general_notes'] ?? null,
        ]);

        $this->syncIndividualRelations($followup, $planData, $studentData);

        // Sync individual activities
        $this->syncActivitiesForIndividual($followup->id, $planData['activities'] ?? []);
    }

    private function syncIndividualRelations(StudentWeeklyFollowup $followup, array $planData, array $studentData): void
    {
        // Memorization sections
        $this->syncMemorization($followup, 'newMemorizations', $planData['new_memorization'] ?? [], [
            'average_level' => $studentData['new_memorization_level'] ?? null,
        ]);
        $this->syncMemorization($followup, 'revisions', $planData['revision'] ?? [], [
            'average_level' => $studentData['revision_level'] ?? null,
        ]);
        $this->syncMemorization($followup, 'oldMemorizations', $planData['old_memorization'] ?? [], [
            'average_level' => $studentData['old_memorization_level'] ?? null,
        ]);

        // Simple assessments
        $this->syncSimpleAssessment($followup, 'discipline', $studentData['discipline_level'] ?? null, $planData['discipline_achievement'] ?? null);
        $this->syncSimpleAssessment($followup, 'tajweedAssessment', $studentData['tajweed_level'] ?? null, $planData['tajweed_achievement'] ?? null);
        $this->syncSimpleAssessment($followup, 'foundationLevel', $studentData['foundation_level_level'] ?? null, $planData['foundation_level_achievement'] ?? null);

        // Educational lesson
        $this->syncEducationalLesson($followup, $planData['educational_lesson_id'] ?? null, $planData, $studentData);

        // ⬅️ توليد وتخزين التوصية (يغطي حالتي الإنشاء والتحديث الفردي معًا)
        $this->recommendationService->generateAndStore($followup);
    }

    // ═══════════════════════════════════════════════════════════════
    // Data Building - Group
    // ═══════════════════════════════════════════════════════════════

    public function buildGroupViewData(string $batchId): array
    {
        $followups = StudentWeeklyFollowup::byBatch($batchId)
            ->with([
                'student',
                'circle',
                'teacher.user',
                'newMemorizations.fromSurah',
                'newMemorizations.toSurah',
                'revisions.fromSurah',
                'revisions.toSurah',
                'oldMemorizations.fromSurah',
                'oldMemorizations.toSurah',
                'educationalLessonAssessment.lesson',
                'discipline',
                'tajweedAssessment',
                'foundationLevel',
            ])
            ->get();

        if ($followups->isEmpty()) {
            abort(404);
        }

        $first = $followups->first();

        $planData = [
            'circle_id'   => $first->circle_id,
            'teacher_id'  => $first->teacher_id,
            'week_start'  => $first->week_start->format('Y-m-d'),
            'week_end'    => $first->week_end->format('Y-m-d'),
            'study_days'  => $first->study_days ?? [],
        ];

        $newMem = $first->newMemorizations;
        if ($newMem) {
            $planData['new_memorization'] = [
                'from_surah_id'       => $newMem->plan_from_surah_id,
                'from_ayah'           => $newMem->plan_from_ayah,
                'to_surah_id'         => $newMem->plan_to_surah_id,
                'to_ayah'             => $newMem->plan_to_ayah,
                'plan_comparison'     => $newMem->plan_comparison,
                'progress_difference' => $newMem->progress_difference,
                'notes'               => $newMem->notes,
            ];
        }

        $rev = $first->revisions;
        if ($rev) {
            $planData['revision'] = [
                'from_surah_id'       => $rev->plan_from_surah_id,
                'from_ayah'           => $rev->plan_from_ayah,
                'to_surah_id'         => $rev->plan_to_surah_id,
                'to_ayah'             => $rev->plan_to_ayah,
                'plan_comparison'     => $rev->plan_comparison,
                'progress_difference' => $rev->progress_difference,
                'notes'               => $rev->notes,
            ];
        }

        $oldMem = $first->oldMemorizations;
        if ($oldMem) {
            $planData['old_memorization'] = [
                'from_surah_id'       => $oldMem->plan_from_surah_id,
                'from_ayah'           => $oldMem->plan_from_ayah,
                'to_surah_id'         => $oldMem->plan_to_surah_id,
                'to_ayah'             => $oldMem->plan_to_ayah,
                'plan_comparison'     => $oldMem->plan_comparison,
                'progress_difference' => $oldMem->progress_difference,
                'notes'               => $oldMem->notes,
            ];
        }

        $planData['discipline_achievement']       = $first->discipline?->notes;
        $planData['tajweed_achievement']           = $first->tajweedAssessment?->notes;
        $planData['foundation_level_achievement']  = $first->foundationLevel?->notes;

        $studentsData = $followups->map(function ($followup) {
            $newMem = $followup->newMemorizations;
            $rev = $followup->revisions;
            $oldMem = $followup->oldMemorizations;
            $lessonAssessment = $followup->educationalLessonAssessment;

            return [
                'student_id'     => $followup->student_id,
                'student_name'   => $followup->student?->name,
                'general_notes'  => $followup->notes,

                'discipline_level'         => $followup->discipline?->level,
                'tajweed_level'             => $followup->tajweedAssessment?->level,
                'foundation_level_level'    => $followup->foundationLevel?->level,
                'new_memorization_level'    => $newMem?->average_level,
                'revision_level'            => $rev?->average_level,
                'old_memorization_level'    => $oldMem?->average_level,
                'educational_lesson_level'  => $lessonAssessment?->level,
                'educational_lesson_notes'  => $lessonAssessment?->notes,

                'new_memorization' => [
                    'average_level' => $newMem?->average_level,
                    'notes'         => $newMem?->notes,
                ],
                'revision' => [
                    'average_level' => $rev?->average_level,
                    'notes'         => $rev?->notes,
                ],
                'old_memorization' => [
                    'average_level' => $oldMem?->average_level,
                    'notes'         => $oldMem?->notes,
                ],
            ];
        })->toArray();

        $activitiesData = StudentActivity::where('follow_id', $batchId)
            ->where('weekly_followup_id', $first->id)
            ->get(['activity_type', 'activity_name', 'activity_date', 'notes'])
            ->map(fn($a) => [
                'activity_type' => $a->activity_type,
                'activity_name' => $a->activity_name,
                'activity_date' => $a->activity_date?->format('Y-m-d'),
                'notes'         => $a->notes,
            ])
            ->toArray();

        return [
            'batch_id'        => $batchId,
            'plan_data'       => $planData,
            'students_data'   => $studentsData,
            'activities_data' => $activitiesData,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // Data Building - Individual
    // ═══════════════════════════════════════════════════════════════

    public function buildIndividualViewData(StudentWeeklyFollowup $followup): array
    {
        $followup->load([
            'newMemorizations.fromSurah',
            'newMemorizations.toSurah',
            'revisions.fromSurah',
            'revisions.toSurah',
            'oldMemorizations.fromSurah',
            'oldMemorizations.toSurah',
            'educationalLessonAssessment.lesson',
            'discipline',
            'tajweedAssessment',
            'foundationLevel',
            'activities',
        ]);

        $newMem = $followup->newMemorizations;
        $rev = $followup->revisions;
        $oldMem = $followup->oldMemorizations;

        // Plan data (flat structure matching form fields)
        $planData = [
            'circle_id'   => $followup->circle_id,
            'teacher_id'  => $followup->teacher_id,
            'week_start'  => $followup->week_start->format('Y-m-d'),
            'week_end'    => $followup->week_end->format('Y-m-d'),
            'study_days'  => $followup->study_days ?? [],
        ];

        if ($newMem) {
            $planData['new_memorization'] = [
                'from_surah_id'       => $newMem->plan_from_surah_id,
                'from_ayah'           => $newMem->plan_from_ayah,
                'to_surah_id'         => $newMem->plan_to_surah_id,
                'to_ayah'             => $newMem->plan_to_ayah,
                'plan_comparison'     => $newMem->plan_comparison,
                'progress_difference' => $newMem->progress_difference,
                'notes'               => $newMem->notes,
            ];
        }

        if ($rev) {
            $planData['revision'] = [
                'from_surah_id'       => $rev->plan_from_surah_id,
                'from_ayah'           => $rev->plan_from_ayah,
                'to_surah_id'         => $rev->plan_to_surah_id,
                'to_ayah'             => $rev->plan_to_ayah,
                'plan_comparison'     => $rev->plan_comparison,
                'progress_difference' => $rev->progress_difference,
                'notes'               => $rev->notes,
            ];
        }

        if ($oldMem) {
            $planData['old_memorization'] = [
                'from_surah_id'       => $oldMem->plan_from_surah_id,
                'from_ayah'           => $oldMem->plan_from_ayah,
                'to_surah_id'         => $oldMem->plan_to_surah_id,
                'to_ayah'             => $oldMem->plan_to_ayah,
                'plan_comparison'     => $oldMem->plan_comparison,
                'progress_difference' => $oldMem->progress_difference,
                'notes'               => $oldMem->notes,
            ];
        }

        $planData['discipline_achievement']       = $followup->discipline?->notes;
        $planData['tajweed_achievement']           = $followup->tajweedAssessment?->notes;
        $planData['foundation_level_achievement']  = $followup->foundationLevel?->notes;
        $planData['educational_lesson_id']         = $followup->educationalLessonAssessment?->educational_lesson_id;

        // Student data (single student)
        $studentData = [
            'student_id'     => $followup->student_id,
            'student_name'   => $followup->student?->name,
            'general_notes'  => $followup->notes,

            'discipline_level'         => $followup->discipline?->level,
            'tajweed_level'             => $followup->tajweedAssessment?->level,
            'foundation_level_level'    => $followup->foundationLevel?->level,
            'new_memorization_level'    => $newMem?->average_level,
            'revision_level'            => $rev?->average_level,
            'old_memorization_level'    => $oldMem?->average_level,
            'educational_lesson_level'  => $followup->educationalLessonAssessment?->level,
            'educational_lesson_notes'  => $followup->educationalLessonAssessment?->notes,
        ];

        $activitiesData = $followup->activities
            ->map(fn($a) => [
                'activity_type' => $a->activity_type,
                'activity_name' => $a->activity_name,
                'activity_date' => $a->activity_date?->format('Y-m-d'),
                'notes'         => $a->notes,
            ])
            ->toArray();

        return [
            'plan_data'       => $planData,
            'student_data'    => $studentData,
            'activities_data' => $activitiesData,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // Single Row Update (من StudentWeeklyFollowupController::update)
    // ═══════════════════════════════════════════════════════════════

    public function updateSingleRow(StudentWeeklyFollowup $followup, array $validated): void
    {
        $followup->update([
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->syncMemorization(
            $followup,
            'newMemorizations',
            $this->extractRangeSection($validated, 'memorization'),
            ['average_level' => $validated['new_memorization_level'] ?? null]
        );

        $this->syncMemorization(
            $followup,
            'revisions',
            $this->extractRangeSection($validated, 'revision'),
            ['average_level' => $validated['revision_level'] ?? null]
        );

        $this->syncMemorization(
            $followup,
            'oldMemorizations',
            $this->extractRangeSection($validated, 'old_revision'),
            ['average_level' => $validated['old_memorization_level'] ?? null]
        );

        $this->syncSimpleAssessment(
            $followup,
            'discipline',
            $validated['discipline_level'] ?? null,
            $validated['discipline_achievement'] ?? null
        );

        $this->syncSimpleAssessment(
            $followup,
            'tajweedAssessment',
            $validated['tajweed_level'] ?? null,
            $validated['tajweed_achievement'] ?? null
        );

        $this->syncSimpleAssessment(
            $followup,
            'foundationLevel',
            $validated['foundation_level_level'] ?? null,
            $validated['foundation_level_achievement'] ?? null
        );

        // القسم التعليمي هنا شرطي (لا يُحذف تلقائيًا لو مفيش educational_lesson_id
        // لأن الفورم الفردي القديم ما كانش بيبعت educational_lesson_id أصلاً)
        if (
            $followup->educationalLessonAssessment()->exists()
            || !empty($validated['educational_lesson_level'])
            || !empty($validated['educational_lesson_notes'])
        ) {
            $followup->educationalLessonAssessment()->updateOrCreate(
                ['weekly_followup_id' => $followup->id],
                [
                    'level' => $validated['educational_lesson_level'] ?? null,
                    'notes' => $validated['educational_lesson_notes'] ?? null,
                ]
            );
        }

        $this->recommendationService->generateAndStore($followup);
    }

    /**
     * يستخرج بيانات نطاق حفظ/مراجعة (from/to surah/ayah) من مصفوفة الـ validated
     * بناءً على بادئة (prefix) الحقول، ليتوافق مع بنية syncMemorization/mergePlanAndStudentData.
     *
     * مثال: extractRangeSection($validated, 'memorization') تستخرج
     * memorization_from_surah_id, memorization_from_ayah, ... إلخ
     */
    private function extractRangeSection(array $validated, string $prefix): array
    {
        return [
            'from_surah_id'       => $validated["{$prefix}_from_surah_id"] ?? null,
            'from_ayah'           => $validated["{$prefix}_from_ayah"] ?? null,
            'to_surah_id'         => $validated["{$prefix}_to_surah_id"] ?? null,
            'to_ayah'             => $validated["{$prefix}_to_ayah"] ?? null,
            'plan_comparison'     => $validated["{$prefix}_plan_comparison"] ?? null,
            'progress_difference' => $validated["{$prefix}_progress_difference"] ?? null,
            'notes'               => $validated["{$prefix}_notes"] ?? null,
        ];
    }
}
