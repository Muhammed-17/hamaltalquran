<?php

namespace App\Http\Controllers;

use App\Models\FavoriteStudent;
use App\Models\Student;
use App\Models\Circle;
use App\Models\Teacher;
use App\Services\UserAccessService;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function store(Request $request, Student $student)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
            'teacher_id' => 'nullable|exists:teachers,id',
        ], [
            'reason.required' => 'لازم تكتب سبب إضافة الطالب للمفضلة',
        ]);

        $user = $request->user();
        $isTopManagement = $user->hasRole(['admin', 'general_manager']);

        $teacherId = $isTopManagement
            ? $request->input('teacher_id')
            : app(UserAccessService::class)->teacher($user)?->id;

        FavoriteStudent::updateOrCreate(
            ['student_id' => $student->id, 'teacher_id' => $teacherId],
            ['circle_id' => $student->circle_id, 'reason' => $request->reason]
        );

        return back()->with('success', 'تمت إضافة الطالب للمفضلة');
    }

    public function destroy(Request $request, Student $student)
    {
        $user = $request->user();
        $isTopManagement = $user->hasRole(['admin', 'general_manager']);

        $teacherId = $isTopManagement
            ? $request->input('teacher_id')
            : app(UserAccessService::class)->teacher($user)?->id;

        FavoriteStudent::where('student_id', $student->id)
            ->where('teacher_id', $teacherId)
            ->delete();

        return back()->with('success', 'تم حذف الطالب من المفضلة');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $access = app(UserAccessService::class);
        $teacher = $access->teacher($user);
        $isTopManagement = $user->hasRole(['admin', 'general_manager']);

        $teachers = $isTopManagement ? Teacher::with('user')->get() : collect();
        $circles = $access->accessibleCircles($user)->get();
        
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
        ]);

        $favorites = FavoriteStudent::with(['student', 'circle', 'teacher.user'])
            ->when(!$isTopManagement, function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher?->id);
            })
            ->when($request->filled('teacher_id'), function ($q) use ($request) {
                $q->where('teacher_id', $request->teacher_id);
            })
            ->when($request->filled('circle_id'), function ($q) use ($request) {
                $q->where('circle_id', $request->circle_id);
            })
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->date_to);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->whereHas('student', function ($sq) use ($request) {
                    $sq->where('name', 'like', '%' . $request->search . '%');
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('students.favorite', compact('favorites', 'teachers', 'circles', 'isTopManagement'));
    }
}
