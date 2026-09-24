<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeeklyFollowup\StoreGroupWeeklyFollowupRequest;
use App\Http\Requests\WeeklyFollowup\UpdateGroupWeeklyFollowupRequest;
use App\Http\Requests\WeeklyFollowup\StoreIndividualWeeklyFollowupRequest;
use App\Http\Requests\WeeklyFollowup\UpdateIndividualWeeklyFollowupRequest;
use App\Http\Requests\WeeklyFollowup\UpdateSingleWeeklyFollowupRequest;
use App\Models\StudentWeeklyFollowup;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Surah;
use App\Models\EducationalLesson;
use App\Models\Scopes\CenterScope;
use App\Services\WeeklyFollowupService;
use App\Services\FollowupFormContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Carbon\Carbon;

class StudentWeeklyFollowupController extends Controller
{
    private const ASSESSMENT_TYPES = [
        'الانضباط',
        'التجويد',
        'الحضور',
        'الواجب',
        'التفاعل',
    ];

    public function __construct(
        private WeeklyFollowupService $followupService,
        private FollowupFormContextService $formContext,
    ) {}

    // =============================================================
    // INDEX
    // =============================================================
    public function indexGroup(Request $request): View
    {
        $this->authorize('viewAny', StudentWeeklyFollowup::class);

        $groupBatches = $this->buildGroupBatchesQuery($request)->paginate(
            $request->get('per_page', 15)
        );

        $stats = $this->buildStats();
        $filters = $this->buildFilterData();
        $circleAccess = $this->resolveCircleTypeAccess();

        return view('student_weekly_followups.index_group', compact('groupBatches', 'stats', 'filters', 'circleAccess'));
    }
    public function indexIndividual(Request $request): View
    {
        $this->authorize('viewAny', StudentWeeklyFollowup::class);

        $individualPlans = $this->buildIndividualPlansQuery($request)->paginate(
            $request->get('per_page', 15)
        );

        $stats = $this->buildStats();
        $filters = $this->buildFilterData();
        $circleAccess = $this->resolveCircleTypeAccess();

        return view('student_weekly_followups.index_individual', compact('individualPlans', 'stats', 'filters', 'circleAccess'));
    }

    // =============================================================
    // SHOW GROUP (by batch_id)
    // =============================================================
    public function showGroup(string $batchId): View
    {
        $viewData = $this->followupService->buildGroupViewData($batchId);
        $sample = StudentWeeklyFollowup::where('batch_id', $batchId)->firstOrFail();
        $this->authorize('view', $sample);

        $context = $this->formContext->forGroup();
        $educationalLesson = $this->latestEducationalLesson($sample->center_id);

        $selectedCircleId = $viewData['plan_data']['circle_id'];
        $students = $this->studentsForCircleId($selectedCircleId);

        return view('student_weekly_followups.show_group', array_merge($viewData, $context, [
            'mode' => 'show',
            'educationalLesson' => $educationalLesson,
            'batchId' => $batchId,
            'students' => $students,
            'selectedCircleId' => $selectedCircleId,
        ]));
    }

    // =============================================================
    // CREATE GROUP
    // =============================================================
    public function createGroup(Request $request): View
    {
        $this->authorize('create', StudentWeeklyFollowup::class);

        $weekDates = $this->calculateCurrentWeek();
        $context = $this->formContext->forGroup();
        $centerId = $this->formContext->resolveCurrentCenterId();
        $educationalLesson = $this->latestEducationalLesson($centerId);

        $selectedCircleId = $request->input('circle_id', old('circle_id'));
        $students = $this->studentsForCircleId($selectedCircleId);

        $batchId = null;

        return view('student_weekly_followups.create_group', array_merge($context, compact(
            'weekDates',
            'students',
            'selectedCircleId',
            'batchId',
            'educationalLesson'
        )));
    }

    // =============================================================
    // STORE GROUP
    // =============================================================
    public function storeGroup(StoreGroupWeeklyFollowupRequest $request): RedirectResponse
    {
        $this->authorize('create', StudentWeeklyFollowup::class);

        $validated = $request->validated();
        $centerId = $this->formContext->resolveCurrentCenterId();

        $studentIds = collect($validated['students'])->pluck('student_id')->toArray();

        if ($error = $this->validateGroupStudents($studentIds, $validated['circle_id'], $validated['week_start'])) {
            return back()->withInput()->with('error', $error);
        }

        DB::beginTransaction();
        try {

            $planData = $this->buildGroupPlanData($validated, $centerId);

            $batchId = $this->followupService->createBatch($planData, $validated['students'], $validated['activities'] ?? []);
            DB::commit();

            $count = count($validated['students']);
            return redirect()
                ->route('student-weekly-followups.show-group', $batchId)
                ->with('success', "تم إنشاء {$count} متابعة أسبوعية بنجاح لطلاب الحلقة.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء الحفظ: ' . $e->getMessage());
        }
    }

    // =============================================================
    // EDIT GROUP (by batch_id)
    // =============================================================
    public function editGroup(string $batchId): View
    {
        $viewData = $this->followupService->buildGroupViewData($batchId);

        $sample = StudentWeeklyFollowup::where('batch_id', $batchId)->firstOrFail();
        $this->authorize('update', $sample);

        $context = $this->formContext->forGroup();
        $educationalLesson = $this->latestEducationalLesson($sample->center_id);
        $selectedCircleId = $viewData['plan_data']['circle_id'];
        $students = $this->studentsForCircleId($selectedCircleId);

        return view('student_weekly_followups.edit_group', array_merge($viewData, $context, compact(
            'students',
            'selectedCircleId',
            'batchId',
            'educationalLesson'
        )));
    }

    // =============================================================
    // UPDATE GROUP
    // =============================================================
    public function updateGroup(UpdateGroupWeeklyFollowupRequest $request, string $batchId): RedirectResponse
    {
        $sample = StudentWeeklyFollowup::where('batch_id', $batchId)->firstOrFail();
        $this->authorize('update', $sample);
        $centerId = $sample->center_id;

        $validated = $request->validated();

        $studentIds = collect($validated['students'])->pluck('student_id')->toArray();

        if ($error = $this->validateGroupStudents($studentIds, $validated['circle_id'], $validated['week_start'], $batchId)) {
            return back()->withInput()->with('error', $error);
        }

        DB::beginTransaction();
        try {

            $planData = $this->buildGroupPlanData($validated, $centerId);

            $this->followupService->updateBatch($batchId, $planData, $validated['students'], $validated['activities'] ?? []);
            DB::commit();

            return redirect()
                ->route('student-weekly-followups.index-group')
                ->with('success', 'تم تحديث المتابعة الجماعية وبياناتها بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء التحديث: ' . $e->getMessage());
        }
    }

    // =============================================================
    // DESTROY GROUP (by batch_id)
    // =============================================================
    public function destroyGroup(string $batchId): RedirectResponse
    {
        $sample = StudentWeeklyFollowup::where('batch_id', $batchId)->firstOrFail();
        $this->authorize('delete', $sample);

        DB::beginTransaction();
        try {
            $this->followupService->deleteBatch($batchId);
            DB::commit();

            return redirect()
                ->route('student-weekly-followups.index-group')
                ->with('success', 'تم حذف المتابعة الجماعية وكل بياناتها المرتبطة بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->with('error', 'حدث خطأ أثناء الحذف: ' . $e->getMessage());
        }
    }

    // =============================================================
    // INDIVIDUAL PLANS
    // =============================================================
    public function createIndividual(): View
    {
        $this->authorize('create', StudentWeeklyFollowup::class);

        $weekDates = $this->calculateCurrentWeek();
        $context = $this->formContext->forIndividual(weekStart: $weekDates['week_start']);
        $centerId = $this->formContext->resolveCurrentCenterId();
        $educationalLesson = $this->latestEducationalLesson($centerId);

        return view('student_weekly_followups.create_individual', array_merge($context, compact(
            'weekDates',
            'educationalLesson'
        )));
    }

    public function storeIndividual(StoreIndividualWeeklyFollowupRequest $request): RedirectResponse
    {
        $this->authorize('create', StudentWeeklyFollowup::class);

        $validated = $request->validated();

        $circleId = $this->resolveCircleId($validated);

        DB::beginTransaction();
        try {
            $planData = [
                'center_id'        => $this->formContext->resolveCurrentCenterId(),
                'circle_id'        => $circleId,
                'teacher_id'       => $validated['teacher_id'],
                'week_start'       => $validated['week_start'],
                'week_end'         => $validated['week_end'],
                'study_days'       => $validated['study_days'] ?? [],
                'new_memorization' => $this->buildMemorizationSection($validated, 'memorization'),
                'revision'         => $this->buildMemorizationSection($validated, 'revision'),
                'old_memorization' => $this->buildMemorizationSection($validated, 'old_revision'),
                'educational_lesson_id' => $validated['educational_lesson_id'] ?? null,
                'discipline_achievement'       => $validated['discipline_achievement'] ?? null,
                'tajweed_achievement'          => $validated['tajweed_achievement'] ?? null,
                'foundation_level_achievement' => $validated['foundation_level_achievement'] ?? null,
                'activities'             => $validated['activities'] ?? [],  // ⬅️ ADDED
            ];


            $studentData = $this->buildStudentAssessmentData($validated);

            $followup = $this->followupService->createIndividualFollowup($planData, $studentData);

            // ⬅️ Sync activities for individual followup
            $this->followupService->syncActivitiesForIndividual($followup->id, $validated['activities'] ?? []);

            DB::commit();

            return redirect()
                ->route('student-weekly-followups.index-individual')
                ->with('success', 'تم إنشاء المتابعة الفردية وبياناتها بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء الحفظ: ' . $e->getMessage());
        }
    }
    public function editIndividual(StudentWeeklyFollowup $studentWeeklyFollowup): View
    {
        $this->authorize('update', $studentWeeklyFollowup);
        abort_unless($studentWeeklyFollowup->plan_type === 'individual', 404);

        $context = $this->formContext->forIndividual($studentWeeklyFollowup, $studentWeeklyFollowup->week_start);
        $educationalLesson = $this->latestEducationalLesson($studentWeeklyFollowup->center_id);
        $studentWeeklyFollowup->load($this->individualRelations());

        return view('student_weekly_followups.edit_individual', array_merge($context, compact(
            'studentWeeklyFollowup',
            'educationalLesson'
        )));
    }

    public function updateIndividual(UpdateIndividualWeeklyFollowupRequest $request, StudentWeeklyFollowup $studentWeeklyFollowup): RedirectResponse
    {
        $this->authorize('update', $studentWeeklyFollowup);

        if ($studentWeeklyFollowup->plan_type !== 'individual') {
            abort(404);
        }

        $validated = $request->validated();

        $circleId = $this->resolveCircleId($validated);

        DB::beginTransaction();
        try {
            $planData = [
                'circle_id'        => $circleId,
                'teacher_id'       => $validated['teacher_id'],
                'week_start'       => $validated['week_start'],
                'week_end'         => $validated['week_end'],
                'study_days'       => $validated['study_days'] ?? [],
                'new_memorization' => $this->buildMemorizationSection($validated, 'memorization'),
                'revision'         => $this->buildMemorizationSection($validated, 'revision'),
                'old_memorization' => $this->buildMemorizationSection($validated, 'old_revision'),
                'educational_lesson_id' => $validated['educational_lesson_id'] ?? null,
                'discipline_achievement'       => $validated['discipline_achievement'] ?? null,
                'tajweed_achievement'          => $validated['tajweed_achievement'] ?? null,
                'foundation_level_achievement' => $validated['foundation_level_achievement'] ?? null,
                'activities'             => $validated['activities'] ?? [],  // ⬅️ ADDED
            ];

            $studentData = $this->buildStudentAssessmentData($validated);

            $this->followupService->updateIndividualFollowup($studentWeeklyFollowup, $planData, $studentData);

            DB::commit();

            return redirect()
                ->route('student-weekly-followups.index-individual')
                ->with('success', 'تم تحديث المتابعة الفردية وبياناتها بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء التحديث: ' . $e->getMessage());
        }
    }

    // =============================================================
    // SHOW INDIVIDUAL
    // =============================================================
    public function showIndividual(StudentWeeklyFollowup $studentWeeklyFollowup): View
    {
        $this->authorize('view', $studentWeeklyFollowup);
        abort_unless($studentWeeklyFollowup->plan_type === 'individual', 404);

        $context = $this->formContext->forIndividual($studentWeeklyFollowup);

        $studentWeeklyFollowup->load($this->individualRelations());

        return view('student_weekly_followups.show_individual', array_merge($context, compact(
            'studentWeeklyFollowup'
        )));
    }

    private function individualRelations(): array
    {
        return [
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
            'student',
            'circle',
            'teacher.user',
        ];
    }

    // =============================================================
    // SHOW SINGLE ROW (works for any plan_type — individual or a
    // single student's row inside a group batch)
    // =============================================================
    public function show(StudentWeeklyFollowup $studentWeeklyFollowup): View
    {
        $this->authorize('view', $studentWeeklyFollowup);

        $studentWeeklyFollowup->load(
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
            'student',
            'circle',
            'teacher.user',
        );

        $surahs = Surah::orderBy('id')->get();

        // ✅ حضور وغياب هذا الأسبوع فقط (بين week_start و week_end)
        $attendances = $studentWeeklyFollowup->student
            ? $studentWeeklyFollowup->student->attendances()
            ->whereBetween('date', [
                $studentWeeklyFollowup->week_start,
                $studentWeeklyFollowup->week_end,
            ])
            ->get()
            : collect();

        $totalAttendance = $attendances->count();
        $presentCount    = $attendances->where('status', 'present')->count();
        $lateCount       = $attendances->where('status', 'late')->count();
        $absentCount     = $attendances->where('status', 'absent')->count();
        $excusedCount    = $attendances->where('status', 'excused')->count();
        $attendanceRate  = $totalAttendance > 0
            ? round((($presentCount + $lateCount) / $totalAttendance) * 100)
            : 0;

        return view('student_weekly_followups.show', compact(
            'studentWeeklyFollowup',
            'surahs',
            'presentCount',
            'lateCount',
            'absentCount',
            'excusedCount',
            'attendanceRate'
        ));
    }

    // =============================================================
    // EDIT SINGLE ROW
    // =============================================================
    public function edit(StudentWeeklyFollowup $studentWeeklyFollowup): View
    {
        $this->authorize('update', $studentWeeklyFollowup);

        $studentWeeklyFollowup->load(
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
            'activities'
        );

        $surahs = Surah::orderBy('id')->get();

        return view('student_weekly_followups.edit_single', compact('studentWeeklyFollowup', 'surahs'));
    }

    // =============================================================
    // UPDATE SINGLE ROW
    // =============================================================
    public function update(UpdateSingleWeeklyFollowupRequest $request, StudentWeeklyFollowup $studentWeeklyFollowup): RedirectResponse
    {
        $this->authorize('update', $studentWeeklyFollowup);

        DB::beginTransaction();
        try {
            $this->followupService->updateSingleRow($studentWeeklyFollowup, $request->validated());
            DB::commit();

            return redirect()
                ->route('students.show', $studentWeeklyFollowup->student_id)
                ->with('success', 'تم تحديث بيانات المتابعة الأسبوعية بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء التحديث: ' . $e->getMessage());
        }
    }

    // =============================================================
    // DESTROY SINGLE ROW
    // =============================================================
    public function destroy(StudentWeeklyFollowup $studentWeeklyFollowup): RedirectResponse
    {
        $this->authorize('delete', $studentWeeklyFollowup);

        $studentId = $studentWeeklyFollowup->student_id;

        DB::beginTransaction();
        try {
            $this->followupService->deleteFollowupWithRelations($studentWeeklyFollowup);
            DB::commit();

            return redirect()
                ->route('student-weekly-followups.index-individual', $studentId)
                ->with('success', 'تم حذف المتابعة الأسبوعية بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->with('error', 'حدث خطأ أثناء الحذف: ' . $e->getMessage());
        }
    }

    // =============================================================
    // Private: Index Query Builders
    // =============================================================
    private function buildGroupBatchesQuery(Request $request)
    {
        return StudentWeeklyFollowup::groupPlans()
            ->select('batch_id')
            ->selectRaw('MIN(id) as id')
            ->selectRaw('MAX(circle_id) as circle_id')
            ->selectRaw('MAX(teacher_id) as teacher_id')
            ->selectRaw('MAX(week_start) as week_start')
            ->selectRaw('MAX(week_end) as week_end')
            ->selectRaw('MAX(created_at) as created_at')
            ->selectRaw('COUNT(*) as students_count')
            ->with(['circle', 'teacher.user'])
            ->when($request->filled('center_id'), fn($q) => $q->where('center_id', $request->center_id))
            ->when($request->filled('branch_id'), fn($q) => $q->whereHas(       // ← جديد
                'circle',
                fn($cq) => $cq->where('branch_id', $request->branch_id)
            ))
            ->when($request->filled('circle_id'), fn($q) => $q->where('circle_id', $request->circle_id))
            ->when($request->filled('teacher_id'), fn($q) => $q->where('teacher_id', $request->teacher_id))
            ->when($request->filled('week_start'), fn($q) => $q->where('week_start', '>=', $request->week_start))
            ->when($request->filled('week_end'), fn($q) => $q->where('week_end', '<=', $request->week_end))
            ->groupBy('batch_id')
            ->orderBy('week_start', 'desc');
    }

    private function buildIndividualPlansQuery(Request $request)
    {
        return StudentWeeklyFollowup::individualPlans()
            ->with([
                'circle',
                'student',
                'teacher.user',
                'newMemorizations.fromSurah',
                'newMemorizations.toSurah',
            ])
            ->when($request->filled('center_id'), fn($q) => $q->where('center_id', $request->center_id))
            ->when($request->filled('branch_id'), fn($q) => $q->whereHas(       // ← جديد
                'circle',
                fn($cq) => $cq->where('branch_id', $request->branch_id)
            ))
            ->when($request->filled('circle_id'), fn($q) => $q->where('circle_id', $request->circle_id))
            ->when($request->filled('student_id'), fn($q) => $q->where('student_id', $request->student_id))
            ->when($request->filled('teacher_id'), fn($q) => $q->where('teacher_id', $request->teacher_id))
            ->when($request->filled('week_start'), fn($q) => $q->where('week_start', '>=', $request->week_start))
            ->when($request->filled('week_end'), fn($q) => $q->where('week_end', '<=', $request->week_end))
            ->orderBy('week_start', 'desc');
    }

    private function buildStats(): array
    {
        $weekStart = Carbon::now()->startOfWeek(Carbon::SATURDAY);
        $weekEnd   = $weekStart->copy()->addDays(4);

        return [
            'total'         => StudentWeeklyFollowup::count(),
            'group'         => StudentWeeklyFollowup::groupPlans()->count(),
            'individual'    => StudentWeeklyFollowup::individualPlans()->count(),
            'this_week'     => StudentWeeklyFollowup::whereBetween('week_start', [$weekStart, $weekEnd])->count(),
            'group_batches' => StudentWeeklyFollowup::groupPlans()->distinct('batch_id')->count('batch_id'),
        ];
    }

    private function buildFilterData(): array
    {
        $user   = auth()->user();
        $access = app(\App\Services\UserAccessService::class);

        // ✅ الحلقات مع branch_id/center_id عشان الفلترة الفورية في المتصفح
        $circles = $access->accessibleCircles($user)->with('branch')->get()
            ->map(fn($c) => (object) [
                'id'        => $c->id,
                'name'      => $c->name,
                'branch_id' => $c->branch_id,
                'center_id' => $c->branch?->center_id,
            ]);

        return [
            'circles'  => $circles,
            'teachers' => $access->accessibleTeachers($user)->get(),
            'centers'  => $access->accessibleCenters($user)->get(),
            'branches' => $access->accessibleBranches($user)->get(['id', 'name', 'center_id']),   // ← أضف center_id هنا
        ];
    }

    // =============================================================
    // Private: Helpers
    // =============================================================
    private function resolveCircleTypeAccess(): array
    {
        $user = auth()->user();

        // admin / general_manager / manager: نطاقهم أوسع من حلقاتهم الشخصية فقط
        if ($user->hasRole(['admin', 'general_manager', 'manager'])) {
            return ['group' => true, 'individual' => true];
        }

        $circleTypes = app(\App\Services\UserAccessService::class)->teacherCircleTypes($user);

        return [
            'group'      => $circleTypes->contains('group'),
            'individual' => $circleTypes->contains('individual'),
        ];
    }

    private function calculateCurrentWeek(): array
    {
        $weekStart = Carbon::now()->startOfWeek(Carbon::SATURDAY);
        $weekEnd   = $weekStart->copy()->addDays(4);

        return [
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end'   => $weekEnd->format('Y-m-d'),
        ];
    }
    private function buildMemorizationSection(array $validated, string $prefix): array
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

    private function studentsForCircleId(?int $circleId)
    {
        if (!$circleId) {
            return collect();
        }

        return Student::withoutGlobalScope(CenterScope::class)
            ->where('circle_id', $circleId)
            ->where('status', 'مقيد')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    private function latestEducationalLesson(?int $centerId): ?EducationalLesson
    {
        return EducationalLesson::withoutGlobalScope(CenterScope::class)
            ->where('center_id', $centerId)
            ->latest()
            ->first();
    }

    private function resolveCircleId(array $validated): ?int
    {
        if (!empty($validated['circle_id'])) {
            return $validated['circle_id'];
        }

        if (!empty($validated['student_id'])) {
            return Student::find($validated['student_id'])?->circle_id;
        }

        return null;
    }

    private function validateGroupStudents(array $studentIds, string $circleId, string $weekStart, ?string $excludeBatchId = null): ?string
    {
        $invalidStudents = $this->followupService->validateStudentsInCircle($studentIds, $circleId);

        if (!empty($invalidStudents)) {
            return 'بعض الطلاب المحددين لا ينتمون إلى الحلقة المختارة.';
        }

        $duplicates = $this->followupService->getDuplicateStudents($studentIds, $weekStart, $excludeBatchId);

        if ($duplicates->isNotEmpty()) {
            return 'الطلاب التاليون لديهم متابعات بالفعل لهذا الأسبوع: ' . $duplicates->implode('، ');
        }

        return null;
    }

    private function buildGroupPlanData(array $validated, int $centerId): array
    {
        return [
            'center_id'        => $centerId,
            'circle_id'        => $validated['circle_id'],
            'teacher_id'       => $validated['teacher_id'],
            'week_start'       => $validated['week_start'],
            'week_end'         => $validated['week_end'],
            'study_days'       => $validated['study_days'] ?? [],
            'new_memorization' => $validated['new_memorization'] ?? [],
            'revision'         => $validated['revision'] ?? [],
            'old_memorization' => $validated['old_memorization'] ?? [],
            'educational_lesson_id' => $validated['educational_lesson_id'] ?? null,
            'discipline_achievement'         => $validated['discipline_achievement'] ?? null,
            'tajweed_achievement'            => $validated['tajweed_achievement'] ?? null,
            'foundation_level_achievement'   => $validated['foundation_level_achievement'] ?? null,
            'educational_lesson_achievement' => $validated['educational_lesson_achievement'] ?? null,
        ];
    }

    private function buildStudentAssessmentData(array $validated): array
    {
        return [
            'student_id'    => $validated['student_id'],
            'general_notes' => $validated['notes'] ?? null,
            'discipline_level'         => $validated['discipline_level'] ?? null,
            'tajweed_level'             => $validated['tajweed_level'] ?? null,
            'foundation_level_level'    => $validated['foundation_level_level'] ?? null,
            'new_memorization_level'    => $validated['new_memorization_level'] ?? null,
            'revision_level'            => $validated['revision_level'] ?? null,
            'old_memorization_level'    => $validated['old_memorization_level'] ?? null,
            'educational_lesson_level'  => $validated['educational_lesson_level'] ?? null,
            'educational_lesson_notes'  => $validated['educational_lesson_notes'] ?? null,
        ];
    }

    public function studentsForCircle(Circle $circle)
    {
        $this->authorize('view', $circle);

        $students = $this->studentsForCircleId($circle->id);

        return response()->json($students);
    }
}
