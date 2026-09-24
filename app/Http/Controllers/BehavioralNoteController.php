<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\BehavioralNote\StoreBehavioralNoteRequest;
use App\Http\Requests\BehavioralNote\UpdateBehavioralNoteRequest;
use App\Http\Requests\BehavioralNote\UpdateBehavioralNoteActionRequest;
use App\Models\BehavioralNote;
use App\Models\Center;
use App\Models\Scopes\CenterScope;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\UserAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller: BehavioralNote
 *
 * Manages behavioral notes for the module.
 */
class BehavioralNoteController extends Controller
{
    public function __construct(
        protected UserAccessService $userAccessService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', BehavioralNote::class);

        $user = auth()->user();

        $behavioralNotes = BehavioralNote::with(['student', 'circle', 'teacher.user'])
            ->when(
                !$user->hasRole(['admin', 'general_manager']),
                function ($q) use ($user) {
                    $circleIds = $this->userAccessService->accessibleCircles($user)->pluck('id');
                    $this->userAccessService->applyScopeByCircleIds($q, 'behavioral_notes', $circleIds);
                }
            )
            ->when($request->q, fn($q) => $q->whereHas(
                'student',
                fn($sq) => $sq->where('name', 'like', "%{$request->q}%")
            ))
            ->when($request->student_id, fn($q) => $q->where('student_id', $request->student_id))
            ->when($request->center_id, fn($q) => $q->whereHas(
                'circle.branch',                                        // ← عدّلها: 'circle' → 'circle.branch'
                fn($bq) => $bq->where('center_id', $request->center_id)
            ))
            ->when($request->branch_id, fn($q) => $q->whereHas(          // ← فلتر الفرع الجديد
                'circle',
                fn($cq) => $cq->where('branch_id', $request->branch_id)
            ))
            ->when($request->circle_id, fn($q) => $q->where('circle_id', $request->circle_id))
            ->when($request->teacher_id, fn($q) => $q->where('teacher_id', $request->teacher_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->incident_from, fn($q) => $q->where('incident_at', '>=', $request->incident_from))
            ->when($request->incident_to, fn($q) => $q->where('incident_at', '<=', $request->incident_to))
            ->orderBy('incident_at', 'desc')
            ->paginate($request->per_page ?? 15);

        $centers  = $user->hasAnyRole(['admin', 'general_manager']) ? Center::orderBy('name')->get() : collect();
        $branches = $user->hasAnyRole(['supervisor', 'manager', 'general_manager', 'admin'])          // ← جديد
            ? $this->userAccessService->accessibleBranches($user)->get()
            : collect();
        $circles  = $user->hasAnyRole(['supervisor', 'manager', 'general_manager', 'admin']) ? $this->userAccessService->accessibleCircles($user)->get() : collect();
        $teachers = $user->hasAnyRole(['supervisor', 'manager', 'general_manager', 'admin'])
            ? $this->userAccessService->accessibleTeachers($user)->get()->reject(fn($t) => optional($t->user)->hasRole('admin'))->values()
            : collect();

        return view('behavioral_notes.index', compact('behavioralNotes', 'centers', 'branches', 'circles', 'teachers'));   // ← أضف 'branches'
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('create', BehavioralNote::class);

        return view('behavioral_notes.create', $this->buildFormData());
    }

    /**
     * Shared data needed by both create and edit forms.
     *
     * `accessibleTeachers()` (in UserAccessService) already returns the right
     * set per role — supervisors only see staff in circles they supervise,
     * managers/general_manager/admin see the whole branch/all branches.
     * Here we only make sure no admin account is ever listed as a selectable
     * teacher/supervisor.
     */
    private function buildFormData(): array
    {
        $user = auth()->user();

        $teachers = $this->userAccessService->accessibleTeachers($user)->get()
            ->reject(fn($t) => optional($t->user)->hasRole('admin'))
            ->values();

        return [
            'circles'  => $this->userAccessService->accessibleCircles($user)->get(),
            'teachers' => $teachers,
            'students' => Student::withoutGlobalScope(CenterScope::class)
                ->where('status', 'مقيد')
                ->select('id', 'name', 'circle_id')
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBehavioralNoteRequest $request): RedirectResponse
    {
        $this->authorize('create', BehavioralNote::class);

        $behavioralNote = BehavioralNote::create($request->validated());

        return redirect()
            ->route('behavioral-notes.index')
            ->with('success', 'تم إنشاء الملاحظة السلوكية بنجاح.');
    }

    /**
     * Display the specified resource.
     */
    public function show(BehavioralNote $behavioralNote): View
    {
        $this->authorize('view', $behavioralNote);

        $behavioralNote->load(['student', 'circle', 'teacher.user']);

        return view('behavioral_notes.show', compact('behavioralNote'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BehavioralNote $behavioralNote): View
    {
        $this->authorize('update', $behavioralNote);

        return view('behavioral_notes.edit', array_merge(
            $this->buildFormData(),
            compact('behavioralNote')
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBehavioralNoteRequest $request, BehavioralNote $behavioralNote): RedirectResponse
    {
        $this->authorize('update', $behavioralNote);

        $behavioralNote->update($request->validated());

        return redirect()
            ->route('behavioral-notes.index')
            ->with('success', 'تم تحديث الملاحظة السلوكية بنجاح.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BehavioralNote $behavioralNote): RedirectResponse
    {
        $this->authorize('delete', $behavioralNote);

        $behavioralNote->delete();

        return redirect()
            ->route('behavioral-notes.index')
            ->with('success', 'تم حذف الملاحظة السلوكية بنجاح.');
    }

    /**
     * Show the form for the supervisor to record the action taken.
     */
    public function editAction(BehavioralNote $behavioralNote): View
    {
        $this->authorize('recordAction', $behavioralNote);

        $behavioralNote->load(['student', 'circle']);
        $behavioralNote->setRelation(
            'teacher',
            Teacher::withoutGlobalScope(CenterScope::class)
                ->with('user')
                ->find($behavioralNote->teacher_id)
        );

        return view('behavioral_notes.action', compact('behavioralNote'));
    }

    /**
     * Store the supervisor's action for the behavioral note.
     */
    public function updateAction(UpdateBehavioralNoteActionRequest $request, BehavioralNote $behavioralNote): RedirectResponse
    {
        $this->authorize('recordAction', $behavioralNote);

        $behavioralNote->update([
            'action_taken' => $request->validated('action_taken'),
            'action_at'    => now(),
            'status'       => $request->validated('status'),
            'follow_up_at' => $request->validated('follow_up_at'),
        ]);

        return redirect()
            ->route('behavioral-notes.index')
            ->with('success', 'تم تسجيل الإجراء المتخذ بنجاح.');
    }
}
