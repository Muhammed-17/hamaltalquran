<?php

namespace App\Http\Controllers;

use App\Http\Requests\SurahTest\StoreSurahTestRequest;
use App\Http\Requests\SurahTest\UpdateSurahTestRequest;
use App\Models\Circle;
use App\Models\Scopes\CenterScope;
use App\Models\Student;
use App\Models\StudentSurahTestResult;
use App\Models\SurahTest;
use App\Models\Surah;
use App\Models\Teacher;
use App\Services\UserAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SurahTestController extends Controller
{
    // =============================================================
    // INDEX (individual)
    // =============================================================
    public function indexIndividual(Request $request): View
    {
        $this->authorize('viewAny', SurahTest::class);

        $access = app(UserAccessService::class);
        $user   = Auth::user();

        $tests = $this->baseIndexQuery($request, 'individual')->paginate(15)->withQueryString();

        $centerOptions = $access->accessibleCenters($user)->get()
            ->map(fn($c) => ['value' => $c->id, 'label' => $c->name])
            ->values();

        $circleOptions = $access->accessibleCircles($user)
            ->where('type', 'individual')
            ->get()
            ->map(fn($c) => ['value' => $c->id, 'label' => $c->name])
            ->values();

        $surahOptions = Surah::orderBy('id')->get()
            ->map(fn($s) => ['value' => $s->id, 'label' => $s->name_arabic])
            ->values();

        return view('surah_tests.index_individual', compact('tests', 'centerOptions', 'circleOptions', 'surahOptions'));
    }

    // =============================================================
    // INDEX (group)
    // =============================================================
    public function indexGroup(Request $request): View
    {
        $this->authorize('viewAny', SurahTest::class);

        $access = app(UserAccessService::class);
        $user   = Auth::user();

        $tests = $this->baseIndexQuery($request, 'group')->paginate(15)->withQueryString();

        $centerOptions = $access->accessibleCenters($user)->get()
            ->map(fn($c) => ['value' => $c->id, 'label' => $c->name])
            ->values();

        $circleOptions = $access->accessibleCircles($user)
            ->where('type', 'group')
            ->get()
            ->map(fn($c) => ['value' => $c->id, 'label' => $c->name])
            ->values();

        $surahOptions = Surah::orderBy('id')->get()
            ->map(fn($s) => ['value' => $s->id, 'label' => $s->name_arabic])
            ->values();

        return view('surah_tests.index_group', compact('tests', 'centerOptions', 'circleOptions', 'surahOptions'));
    }

    // =============================================================
    // Shared query builder — كل المنطق المشترك بين الفردي والجماعي
    // =============================================================
    private function baseIndexQuery(Request $request, string $fixedType)
    {
        $query = SurahTest::with(['surah', 'circle.branch.center', 'teacher.user'])
            ->withAvg('results', 'percentage')
            ->where('test_type', $fixedType)
            ->when($request->filled('center_id'), fn($q) => $q->whereHas(
                'circle.branch',
                fn($cq) => $cq->where('center_id', $request->center_id)
            ))
            ->when($request->filled('circle_id'), fn($q) => $q->where('circle_id', $request->circle_id))
            ->when($request->filled('surah_id'), fn($q) => $q->where('surah_id', $request->surah_id))
            ->when($request->filled('student_name'), fn($q) => $q->whereHas(
                'results.student',
                fn($sq) => $sq->where('name', 'like', '%' . $request->student_name . '%')
            ));

        $allowedSorts = ['test_date', 'surah', 'circle', 'teacher', 'percentage'];
        $sortField    = in_array($request->sort, $allowedSorts) ? $request->sort : 'test_date';
        $sortDir      = $request->dir === 'asc' ? 'asc' : 'desc';

        return match ($sortField) {
            'surah'      => $query->join('surahs', 'surahs.id', '=', 'surah_tests.surah_id')
                ->orderBy('surahs.name_arabic', $sortDir)
                ->select('surah_tests.*'),
            'circle'     => $query->join('circles', 'circles.id', '=', 'surah_tests.circle_id')
                ->orderBy('circles.name', $sortDir)
                ->select('surah_tests.*'),
            'teacher'    => $query->join('teachers', 'teachers.id', '=', 'surah_tests.teacher_id')
                ->join('users', 'users.id', '=', 'teachers.user_id')
                ->orderBy('users.name', $sortDir)
                ->select('surah_tests.*'),
            'percentage' => $query->orderBy('results_avg_percentage', $sortDir),
            default      => $query->orderBy('test_date', $sortDir),
        };
    }

    public function repeatStudents(Request $request): View
    {
        $this->authorize('viewAny', SurahTest::class);

        $access = app(UserAccessService::class);
        $user   = Auth::user();

        $query = StudentSurahTestResult::with(['student.circle', 'surahTest.surah', 'surahTest.teacher.user'])
            ->where('level', 'إعادة');

        if (!$user->hasAnyRole(['admin', 'general_manager'])) {
            $accessibleCircleIds = $access->accessibleCircles($user)->pluck('id');

            $query->whereHas('student', fn($sq) => $sq->whereIntegerInRaw('circle_id', $accessibleCircleIds));
        }

        $results = $query
            ->when($request->filled('center_id'), fn($q) => $q->whereHas(
                'student.circle',
                fn($cq) => $cq->where('center_id', $request->center_id)
            ))
            ->when($request->filled('circle_id'), fn($q) => $q->whereHas(
                'student',
                fn($sq) => $sq->where('circle_id', $request->circle_id)
            ))
            ->when($request->filled('student_name'), fn($q) => $q->whereHas(
                'student',
                fn($sq) => $sq->where('name', 'like', '%' . $request->student_name . '%')
            ))
            ->get();


        $allowedSorts = ['test_date', 'student', 'circle', 'percentage'];
        $sortField    = in_array($request->sort, $allowedSorts) ? $request->sort : 'test_date';
        $sortDir      = $request->dir === 'asc' ? 'asc' : 'desc';

        $results = match ($sortField) {
            'student'    => $results->sortBy(fn($r) => $r->student?->name ?? '', SORT_STRING, $sortDir === 'desc'),
            'circle'     => $results->sortBy(fn($r) => $r->student?->circle?->name ?? '', SORT_STRING, $sortDir === 'desc'),
            'percentage' => $results->sortBy('percentage', SORT_REGULAR, $sortDir === 'desc'),
            default      => $results->sortBy(fn($r) => $r->surahTest?->test_date, SORT_REGULAR, $sortDir === 'desc'),
        };

        $results = $results->values();

        $perPage     = 15;
        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $pagedItems  = $results->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $results = new \Illuminate\Pagination\LengthAwarePaginator(
            $pagedItems,
            $results->count(),
            $perPage,
            $currentPage,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        $centerOptions = $access->accessibleCenters($user)->get()
            ->map(fn($c) => ['value' => $c->id, 'label' => $c->name])
            ->values();

        $circleOptions = $access->accessibleCircles($user)->get()
            ->map(fn($c) => ['value' => $c->id, 'label' => $c->name])
            ->values();

        return view('surah_tests.repeat_students', compact('results', 'centerOptions', 'circleOptions'));
    }
    // =============================================================
    // CREATE
    // =============================================================
    public function create(Request $request): View
    {
        $this->authorize('create', SurahTest::class);
        $canEditPercentage = auth()->user()->can('edit surah test percentage');

        $user   = Auth::user();
        $access = app(UserAccessService::class);

        $type = $request->route('type') ?? $request->query('type', 'individual');

        $circles = $access->accessibleCircles($user)
            ->where('type', $type)
            ->get();

        $teachers = $access->accessibleTeachers($user)->with('user')->get();
        $surahs   = Surah::orderBy('id')->get();

        $view = $type === 'group' ? 'surah_tests.create_group' : 'surah_tests.create_individual';

        return view($view, compact('circles', 'teachers', 'surahs', 'canEditPercentage'));
    }

    // =============================================================
    // STORE
    // =============================================================
    public function store(StoreSurahTestRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Resolve circle_id: explicit for group tests, derived from the
        // student for individual tests.
        $circleId = $validated['circle_id'] ?? null;
        if (!$circleId && $validated['test_type'] === 'individual') {
            $circleId = Student::find($validated['student_id'])?->circle_id;
        }

        $centerId = Circle::withoutGlobalScope(CenterScope::class)
            ->find($circleId)?->branch?->center_id;

        if (!$centerId) {
            return back()
                ->withInput()
                ->with('error', 'تعذر تحديد المركز التابع له هذا الاختبار (الحلقة غير موجودة).');
        }

        DB::beginTransaction();
        try {
            $surahTest = SurahTest::create([
                'test_type'  => $validated['test_type'],
                'center_id'  => $centerId,
                'circle_id'  => $circleId,
                'teacher_id' => $validated['teacher_id'],
                'surah_id'   => $validated['surah_id'],
                'test_date'  => $validated['test_date'],
                'notes'      => $validated['notes'] ?? null,
            ]);

            $now = now();
            $rows = collect($validated['results'])->map(fn($result) => [
                'surah_test_id'   => $surahTest->id,
                'student_id'      => $result['student_id'],
                'prompt_errors'   => $result['prompt_errors'],
                'tashkeel_errors' => $result['tashkeel_errors'],
                'percentage'      => $result['percentage'],
                'level'           => $result['level'] ?? null,
                'notes'           => $result['notes'] ?? null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ])->toArray();

            StudentSurahTestResult::insert($rows);

            DB::commit();
            return redirect()
                ->route($validated['test_type'] === 'group' ? 'surah-tests.index.group' : 'surah-tests.index.individual')
                ->with('success', 'تم إنشاء اختبار السورة بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء الحفظ: ' . $e->getMessage());
        }
    }

    // =============================================================
    // SHOW
    // =============================================================
    public function show(Request $request, SurahTest $surahTest): View
    {
        $this->authorize('view', $surahTest);

        $surahTest->load(['surah', 'circle', 'teacher.user', 'results.student']);
        $user = auth()->user();

        if ($user->hasRole('guardian')) {
            $surahTest->setRelation(
                'results',
                $surahTest->results->filter(
                    fn($result) => $result->student && (int) $result->student->guardian_id === (int) $user->id
                )->values()
            );
        }


        $focusedStudentId = $request->query('student_id');

        if ($focusedStudentId) {
            $surahTest->setRelation(
                'results',
                $surahTest->results->where('student_id', (int) $focusedStudentId)->values()
            );
        }

        $percentages = $surahTest->results->pluck('percentage');

        $stats = [
            'students_count'    => $surahTest->results->count(),
            'average_percentage' => $percentages->isNotEmpty() ? round($percentages->avg()) : 0,
            'highest_percentage' => $percentages->isNotEmpty() ? $percentages->max() : 0,
            'lowest_percentage'  => $percentages->isNotEmpty() ? $percentages->min() : 0,
        ];

        return view('surah_tests.show', compact('surahTest', 'stats', 'focusedStudentId'));
    }
    // =============================================================
    // EDIT
    // =============================================================
    public function edit(SurahTest $surahTest): View
    {
        $this->authorize('update', $surahTest);
        $canEditPercentage = auth()->user()->can('edit surah test percentage');

        $access = app(UserAccessService::class);

        $circles = $access->accessibleCircles(Auth::user())->get();
        $teachers = $access->accessibleTeachers(Auth::user())
            ->with('user')
            ->get();

        $surahs = Surah::orderBy('id')->get();

        $surahTest->load([
            'surah',
            'circle',
            'teacher.user',
            'results.student',
        ]);

        // اختيار صفحة التعديل المناسبة حسب نوع الاختبار المخزّن فعلياً
        $view = $surahTest->test_type === 'group'
            ? 'surah_tests.edit_group'
            : 'surah_tests.edit_individual';

        return view($view, compact(
            'surahTest',
            'circles',
            'teachers',
            'surahs',
            'canEditPercentage',
        ));
    }
    // =============================================================
    // UPDATE
    // =============================================================
    public function update(UpdateSurahTestRequest $request, SurahTest $surahTest): RedirectResponse
    {
        $validated = $request->validated();

        $validIds = $surahTest->results()->pluck('id')->all();
        $incomingIds = collect($validated['results'])->pluck('id')->all();

        if (array_diff($incomingIds, $validIds)) {
            abort(403, 'بعض النتائج المرسلة لا تنتمي لهذا الاختبار.');
        }

        $centerId = Circle::withoutGlobalScope(CenterScope::class)
            ->find($validated['circle_id'])?->branch?->center_id;

        if (!$centerId) {
            return back()
                ->withInput()
                ->with('error', 'تعذر تحديد المركز التابع له هذا الاختبار (الحلقة غير موجودة).');
        }

        DB::beginTransaction();
        try {

            $surahTestChanges = array_filter([
                'teacher_id' => $validated['teacher_id'] != $surahTest->teacher_id ? $validated['teacher_id'] : null,
                'circle_id'  => $validated['circle_id']  != $surahTest->circle_id  ? $validated['circle_id']  : null,
                'center_id'  => $centerId                != $surahTest->center_id  ? $centerId                : null,
                'surah_id'   => $validated['surah_id']   != $surahTest->surah_id   ? $validated['surah_id']   : null,
                'test_date'  => $validated['test_date']  != $surahTest->test_date?->format('Y-m-d') ? $validated['test_date'] : null,
            ], fn($v) => !is_null($v));

            $notesChanged = ($validated['notes'] ?? null) !== $surahTest->notes;
            if ($notesChanged) {
                $surahTestChanges['notes'] = $validated['notes'] ?? null;
            }

            if (!empty($surahTestChanges)) {
                $surahTest->update($surahTestChanges);
            }

            if ($surahTest->test_type === 'individual' && !empty($validated['student_id'])) {
                $firstResult = $surahTest->results->first();
                if ($firstResult && $firstResult->student_id != $validated['student_id']) {
                    $firstResult->update(['student_id' => $validated['student_id']]);
                }
            }

            $existingResults = $surahTest->results()
                ->whereIn('id', $incomingIds)
                ->get()
                ->keyBy('id');

            $results = collect($validated['results'])->filter(function ($result) use ($existingResults) {
                $existing = $existingResults->get($result['id']);

                if (!$existing) {
                    return false;
                }

                return $existing->prompt_errors   != $result['prompt_errors']
                    || $existing->tashkeel_errors != $result['tashkeel_errors']
                    || $existing->percentage      != $result['percentage']
                    || $existing->level           != ($result['level'] ?? null)
                    || $existing->notes           != ($result['notes'] ?? null);
            })->values()->all();

            if (empty($results)) {
                if (empty($surahTestChanges)) {
                    DB::rollBack();
                    return redirect()
                        ->route($surahTest->test_type === 'group' ? 'surah-tests.index.group' : 'surah-tests.index.individual')
                        ->with('info', 'لم يتم إجراء أي تعديل.');
                }

                DB::commit();
                return redirect()
                    ->route($surahTest->test_type === 'group' ? 'surah-tests.index.group' : 'surah-tests.index.individual')
                    ->with('success', 'تم تحديث الاختبار بنجاح.');
            }

            $ids = collect($results)->pluck('id')->all();

            $promptCases    = '';
            $tashkeelCases  = '';
            $percentageCases = '';
            $levelCases     = '';
            $notesCases     = '';
            $bindings       = [];

            foreach ($results as $result) {
                $promptCases     .= 'WHEN ? THEN ? ';
                $tashkeelCases   .= 'WHEN ? THEN ? ';
                $percentageCases .= 'WHEN ? THEN ? ';
                $levelCases      .= 'WHEN ? THEN ? ';
                $notesCases      .= 'WHEN ? THEN ? ';
            }

            $promptBindings = [];
            $tashkeelBindings = [];
            $percentageBindings = [];
            $levelBindings = [];
            $notesBindings = [];

            foreach ($results as $result) {
                $promptBindings[]     = $result['id'];
                $promptBindings[]     = $result['prompt_errors'];

                $tashkeelBindings[]   = $result['id'];
                $tashkeelBindings[]   = $result['tashkeel_errors'];

                $percentageBindings[] = $result['id'];
                $percentageBindings[] = $result['percentage'];

                $levelBindings[]      = $result['id'];
                $levelBindings[]      = $result['level'] ?? null;

                $notesBindings[]      = $result['id'];
                $notesBindings[]      = $result['notes'] ?? null;
            }

            $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));

            $sql = "
                UPDATE student_surah_test_results
                SET
                    prompt_errors = CASE id {$promptCases} END,
                    tashkeel_errors = CASE id {$tashkeelCases} END,
                    percentage = CASE id {$percentageCases} END,
                    level = CASE id {$levelCases} END,
                    notes = CASE id {$notesCases} END,
                    updated_at = ?
                WHERE id IN ({$idPlaceholders})
                AND surah_test_id = ?
            ";

            $bindings = array_merge(
                $promptBindings,
                $tashkeelBindings,
                $percentageBindings,
                $levelBindings,
                $notesBindings,
                [now()],
                $ids,
                [$surahTest->id]
            );

            DB::update($sql, $bindings);

            DB::commit();

            return redirect()
                ->route($surahTest->test_type === 'group' ? 'surah-tests.index.group' : 'surah-tests.index.individual')
                ->with('success', 'تم تحديث نتائج الاختبار بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء التحديث: ' . $e->getMessage());
        }
    }
    // =============================================================
    // DESTROY
    // =============================================================
    public function destroy(SurahTest $surahTest): RedirectResponse
    {
        $this->authorize('delete', $surahTest);

        DB::beginTransaction();
        try {
            // Explicit cleanup in addition to any DB-level cascade,
            // to stay safe regardless of migration configuration.
            $surahTest->results()->delete();
            $surahTest->delete();

            DB::commit();

            return redirect()
                ->route('surah-tests.index.individual')
                ->with('success', 'تم حذف اختبار السورة بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->with('error', 'حدث خطأ أثناء الحذف: ' . $e->getMessage());
        }
    }

    // =============================================================
    // AJAX: Students for a circle (used by the create page JS)
    // =============================================================
    public function studentsForCircle(Circle $circle)
    {
        $this->authorize('view', $circle);

        $students = Student::withoutGlobalScope(CenterScope::class)
            ->where('circle_id', $circle->id)
            ->where('status', 'مقيد')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($students);
    }
}
