<?php

namespace App\Http\Controllers;

use App\Models\Center;
use Illuminate\Http\Request;

class CenterController extends Controller
{
    public function index()
    {
        $centers = Center::all();
        return view('centers.index', compact('centers'));
    }

    public function store(Request $request)
    {
        // ✅ تنظيف الاسم: إزالة "فرع" و"الفرع" من أي مكان في النص + المسافات الزائدة
        $cleanName = $request->input('name', '');

        // حذف "الفرع" أولاً (الأطول) ثم "فرع" لتجنب بقايا الـ "ال"
        $cleanName = preg_replace('/\bالفرع\b/u', ' ', $cleanName);
        $cleanName = preg_replace('/\bفرع\b/u', ' ', $cleanName);

        // إزالة المسافات الزائدة (متعددة، بداية، نهاية)
        $cleanName = preg_replace('/\s+/u', ' ', $cleanName);
        $cleanName = trim($cleanName);

        // ✅ استبدال القيمة في الطلب للتحقق منها
        $request->merge(['name' => $cleanName]);

        $validated = $request->validate([
            'id'              => 'nullable|exists:centers,id',
            'name'            => 'required|string|max:255|unique:centers,name,' . $request->id,
            'established_at'  => 'nullable|date',
        ], [
            'name.required' => 'حقل الاسم مطلوب.',
            'name.unique'   => 'هذا الفرع مسجل بالفعل.',
        ]);

        if (!empty($validated['id'])) {
            $center = Center::findOrFail($validated['id']);
            $center->update([
                'name'           => $validated['name'],
                'established_at' => $validated['established_at'] ?? null,
            ]);
            $msg = 'تم تحديث الفرع بنجاح';
        } else {
            Center::create([
                'name'           => $validated['name'],
                'established_at' => $validated['established_at'] ?? null,
            ]);
            $msg = 'تم إضافة الفرع بنجاح';
        }

        return redirect()->route('centers.index')->with('success', $msg);
    }

    public function show(Center $center)
    {
        $circles = $center->circles()
            ->with(['mainTeachers.user', 'assistantTeachers.user', 'students'])
            ->orderBy('name')
            ->get();

        $totalStudents = $circles->sum(fn($circle) => $circle->students->count());

        $lessons = \App\Models\EducationalLesson::withoutGlobalScope(\App\Models\Scopes\CenterScope::class)
            ->where('center_id', $center->id)
            ->latest()
            ->get();

        $activeLesson = $lessons->first();
        $lessonsHistory = $lessons->slice(1);

        return view('centers.show', compact(
            'center',
            'circles',
            'totalStudents',
            'activeLesson',
            'lessonsHistory'
        ));
    }

    public function storeEducationalLesson(Request $request, Center $center)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
        ], [
            'title.required' => 'حقل عنوان الدرس مطلوب.',
        ]);

        \App\Models\EducationalLesson::create([
            'center_id'   => $center->id,
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'created_by'  => auth()->id(),
        ]);

        return redirect()->route('centers.show', $center)
            ->with('success', 'تم إضافة الدرس التربوي الجديد وأصبح هو النشط.');
    }

    public function updateEducationalLesson(Request $request, \App\Models\EducationalLesson $educationalLesson)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
        ], [
            'title.required' => 'حقل عنوان الدرس مطلوب.',
        ]);

        $educationalLesson->update([
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('centers.show', $educationalLesson->center_id)
            ->with('success', 'تم تحديث الدرس التربوي بنجاح.');
    }

    public function destroyEducationalLesson(\App\Models\EducationalLesson $educationalLesson)
    {
        $centerId = $educationalLesson->center_id;

        $usageCount = \App\Models\StudentWeeklyEducationalLesson::where('educational_lesson_id', $educationalLesson->id)->count();

        if ($usageCount > 0) {
            return redirect()->route('centers.show', $centerId)
                ->with('error', 'لا يمكن حذف هذا الدرس لأنه مستخدم في ' . $usageCount . ' متابعة أسبوعية.');
        }

        $educationalLesson->delete();

        return redirect()->route('centers.show', $centerId)
            ->with('success', 'تم حذف الدرس التربوي بنجاح.');
    }
    public function destroy(Center $center)
    {
        // ✅ حراس حذف: حذف المركز يتسلسل (cascade) إلى فروعه وحلقاته ودروسه
        // واشتراكات حلقاته — كان الفحص على الطلاب المباشرين فقط، فمركز كامل
        // الهيكل بلا طلاب مباشرين كان يُمسح بنقرة واحدة
        $branchesCount = $center->branches()->count();
        if ($branchesCount > 0) {
            return redirect()->route('centers.index')
                ->with('error', 'لا يمكن حذف هذا المركز لأنه مرتبط بـ ' . $branchesCount . ' فرع. يرجى حذف الفروع أو نقلها أولاً.');
        }

        // ✅ استخدم center_id بدل center
        $studentsCount = \App\Models\Student::where('center_id', $center->id)->count();

        if ($studentsCount > 0) {
            return redirect()->route('centers.index')
                ->with('error', 'لا يمكن حذف هذا المركز لأنه مرتبط بـ ' . $studentsCount . ' طالب.');
        }

        $lessonsCount = $center->educationalLessons()->count();
        if ($lessonsCount > 0) {
            return redirect()->route('centers.index')
                ->with('error', 'لا يمكن حذف هذا المركز لوجود ' . $lessonsCount . ' درس تربوي مرتبط به.');
        }

        $center->delete();
        return redirect()->route('centers.index')->with('success', 'تم حذف المركز بنجاح');
    }
}
