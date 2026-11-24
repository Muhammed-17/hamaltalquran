<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentConstructionDetail\StoreStudentConstructionDetailRequest;
use App\Http\Requests\StudentConstructionDetail\UpdateStudentConstructionDetailRequest;
use App\Models\StudentConstructionDetail;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Surah;
use App\Services\UserAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentConstructionDetailController extends Controller
{
    public function __construct(
        private UserAccessService $accessService
    ) {}

    /**
     * إنشاء خطة بناء جديدة
     */
    public function create(Request $request): View|RedirectResponse
    {
        // سياق الحلقة الجماعية: إنشاء خطة الحلقة لمدير النظام فقط (بقراره)
        // سياق الطالب الفردي: بصلاحية إنشاء طالب
        if ($request->filled('circle_id')) {
            abort_unless($request->user()->hasRole('admin'), 403);
        } else {
            abort_unless(auth()->user()?->can('create student'), 403);
        }

        // السياق: إنشاء خطة لحلقة جماعية
        if ($request->filled('circle_id')) {
            $circle = Circle::findOrFail($request->circle_id);

            if (!$this->accessService->canAccessCircle($request->user(), $circle->id)) {
                abort(403);
            }

            $existing = StudentConstructionDetail::where('circle_id', $circle->id)
                ->whereNull('student_id')
                ->exists();

            if ($existing) {
                return redirect()->route('circles.show', $circle)
                    ->with('error', 'الحلقة لديها خطة بناء موجودة بالفعل.');
            }

            $surahs = Surah::all(['id', 'name_arabic', 'number']);

            return view('student_construction_details.create', compact('circle', 'surahs'));
        }

        // السياق: إنشاء خطة لطالب فردي
        if ($request->filled('student_id')) {
            $student = Student::with('circle')->findOrFail($request->student_id);

            if ($student->circle && $student->circle->type === 'group') {
                abort(403, 'الطلاب في الحلقات الجماعية يتبعون خطة الحلقة.');
            }

            if (!$this->accessService->canAccessStudent($request->user(), $student)) {
                abort(403);
            }

            $existing = StudentConstructionDetail::where('student_id', $student->id)->exists();

            if ($existing) {
                return redirect()->route('students.show', $student)->with('error', 'الطالب لديه خطة بناء موجودة بالفعل.');
            }

            $surahs = Surah::all(['id', 'name_arabic', 'number']);

            return view('student_construction_details.create', compact('student', 'surahs'));
        }

        // إعادة توجيه ودّية بدل خطأ 400: إنشاء الخطة يبدأ دائمًا من حلقة أو طالب،
        // فنوجّه المستخدم لقائمة الحلقات مع رسالة تشرح الخطوة التالية.
        return redirect()->route('circles.index')
            ->with('error', 'لإنشاء خطة بناء اختر حلقة أولًا من قائمتها، أو افتح صفحة طالب.');
    }

    /**
     * تخزين خطة البناء
     */
    public function store(StoreStudentConstructionDetailRequest $request): RedirectResponse
    {
        // سياق الحلقة الجماعية: إنشاء خطة الحلقة لمدير النظام فقط (بقراره)
        // سياق الطالب الفردي: بصلاحية إنشاء طالب
        if ($request->filled('circle_id')) {
            abort_unless($request->user()->hasRole('admin'), 403);
        } else {
            abort_unless(auth()->user()?->can('create student'), 403);
        }

        $validated = $request->validated();

        if ($request->filled('circle_id')) {
            $circle = Circle::findOrFail($request->circle_id);

            if (!$this->accessService->canAccessCircle($request->user(), $circle->id)) {
                abort(403);
            }

            $validated['student_id'] = null;
            $validated['circle_id'] = $circle->id;

            StudentConstructionDetail::create($validated);

            return redirect()->route('circles.show', $circle)
                ->with('success', 'تم إنشاء خطة البناء للحلقة بنجاح.');
        }

        if ($request->filled('student_id')) {
            $student = Student::with('circle')->findOrFail($request->student_id);

            if ($student->circle && $student->circle->type === 'group') {
                abort(403, 'الطلاب في الحلقات الجماعية يتبعون خطة الحلقة.');
            }

            if (!$this->accessService->canAccessStudent($request->user(), $student)) {
                abort(403);
            }

            $validated['student_id'] = $student->id;
            $validated['circle_id'] = $student->circle_id;

            StudentConstructionDetail::create($validated);

            return redirect()->route('students.show', $student)
                ->with('success', 'تم إنشاء خطة البناء للطالب بنجاح.');
        }

        return redirect()->route('circles.index')
            ->with('error', 'يجب تحديد حلقة أو طالب لإنشاء خطة بناء.');
    }

    /**
     * عرض خطة البناء
     */
    public function show(StudentConstructionDetail $studentConstructionDetail): View
    {
        abort_unless(auth()->user()?->hasRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher']), 403);

        if (
            !$this->accessService->canAccessStudent(auth()->user(), $studentConstructionDetail->student)
            && !$this->accessService->canAccessCircle(auth()->user(), $studentConstructionDetail->circle_id)
        ) {
            abort(403);
        }

        $studentConstructionDetail->load(['student', 'circle']);

        return view('student_construction_details.show', compact('studentConstructionDetail'));
    }

    /**
     * تعديل خطة البناء
     */
    public function edit(StudentConstructionDetail $studentConstructionDetail): View|RedirectResponse  // ✅ تغيير نوع الإرجاع
    {
        // خطة الطالب الفردي بصلاحية تعديل طالب، وخطة الحلقة الجماعية للأدمن فقط
        abort_unless($studentConstructionDetail->student_id
            ? auth()->user()?->can('edit student')
            : auth()->user()?->hasRole('admin'), 403);

        if (
            !$this->accessService->canAccessStudent(auth()->user(), $studentConstructionDetail->student)
            && !$this->accessService->canAccessCircle(auth()->user(), $studentConstructionDetail->circle_id)
        ) {
            abort(403);
        }

        $studentConstructionDetail->load(['student', 'circle']);

        $detail = $studentConstructionDetail;
        $surahs = Surah::all(['id', 'name_arabic', 'number']);

        if ($detail->circle_id && !$detail->student_id) {
            $circle = $detail->circle;
            return view('student_construction_details.edit', compact('detail', 'circle', 'surahs'));
        }

        if ($detail->student_id) {
            $student = $detail->student;
            return view('student_construction_details.edit', compact('detail', 'student', 'surahs'));
        }

        abort(400);
    }

    /**
     * تحديث خطة البناء
     */
    public function update(UpdateStudentConstructionDetailRequest $request, StudentConstructionDetail $studentConstructionDetail): RedirectResponse
    {
        // خطة الطالب الفردي بصلاحية تعديل طالب، وخطة الحلقة الجماعية للأدمن فقط
        abort_unless($studentConstructionDetail->student_id
            ? $request->user()->can('edit student')
            : $request->user()->hasRole('admin'), 403);

        if (
            !$this->accessService->canAccessStudent($request->user(), $studentConstructionDetail->student)
            && !$this->accessService->canAccessCircle($request->user(), $studentConstructionDetail->circle_id)
        ) {
            abort(403);
        }

        $validated = $request->validated();

        $validated['circle_id'] = $studentConstructionDetail->circle_id;
        $validated['student_id'] = $studentConstructionDetail->student_id;

        $studentConstructionDetail->update($validated);

        $redirect = $studentConstructionDetail->student_id
            ? route('students.show', $studentConstructionDetail->student_id)
            : route('circles.show', $studentConstructionDetail->circle_id);

        return redirect($redirect)
            ->with('success', 'تم تحديث خطة البناء بنجاح.');
    }

    /**
     * حذف خطة البناء
     */
    public function destroy(StudentConstructionDetail $studentConstructionDetail): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        if (
            !$this->accessService->canAccessStudent(auth()->user(), $studentConstructionDetail->student)
            && !$this->accessService->canAccessCircle(auth()->user(), $studentConstructionDetail->circle_id)
        ) {
            abort(403);
        }

        $redirect = $studentConstructionDetail->student_id
            ? route('students.show', $studentConstructionDetail->student_id)
            : route('circles.show', $studentConstructionDetail->circle_id);

        $studentConstructionDetail->delete();

        return redirect($redirect)
            ->with('success', 'تم حذف خطة البناء بنجاح.');
    }
}
