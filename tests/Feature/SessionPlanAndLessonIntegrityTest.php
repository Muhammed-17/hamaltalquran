<?php

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\EducationalLesson;
use App\Models\GroupSessionPlan;
use App\Models\Student;
use App\Models\StudentWeeklyEducationalLesson;
use App\Models\StudentWeeklyFollowup;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| سلامة خطط جلسات المجموعة والدروس التربوية (نوفمبر 2026)
|--------------------------------------------------------------------------
| - CenterScope كان يفلتر الدروس التربوية بـ whereHas('branch') والدروس
|   لا تملك هذه العلاقة: أي صفحة متابعة فيها تقييم درس كانت تنهار 500
|   عند المعلم/المشرف/المدير. الدروس تُفلتر الآن بعمود center_id مباشرة.
| - صفحة قائمة خطط الجلسات كانت ميتة: تعرض متغيرًا غير موجود أصلًا
|   وحقول مخطط قديم — أُعيدت كتابتها على الحقول الحقيقية.
| - علاقة الدروس weeklyFollowups كانت تشير لعمود غير موجود؛ العلاقة
|   الصحيحة weeklyAssessments على جدول التقييمات الأسبوعية.
| - إنشاء/نقل خطط الجلسات مقصور على حلقات نطاق وصول المستخدم.
*/
beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');

    $this->center = Center::create(['name' => 'المركز الأول']);
    $this->branch = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع أول']);
    $this->circle = Circle::create(['branch_id' => $this->branch->id, 'name' => 'حلقة أولى', 'type' => 'group', 'level' => 'مبتدئ']);

    $center2 = Center::create(['name' => 'المركز الثاني']);
    $branch2 = Branch::create(['center_id' => $center2->id, 'name' => 'فرع ثانٍ']);
    $this->foreignCircle = Circle::create(['branch_id' => $branch2->id, 'name' => 'حلقة غريبة', 'type' => 'group', 'level' => 'مبتدئ']);
    $this->center2 = $center2;

    // معلم مرتبط بالحلقة الأولى فقط
    $this->teacherUser = User::factory()->create(['status' => 'active']);
    $this->teacherUser->assignRole('teacher');
    $this->teacher = Teacher::create(['user_id' => $this->teacherUser->id, 'branch_id' => $this->branch->id]);
    DB::table('circle_teacher')->insert([
        'circle_id' => $this->circle->id, 'teacher_id' => $this->teacher->id, 'role' => 'main',
    ]);

    $this->planPayload = [
        'session_name' => 'جلسة التسميع',
        'start_time' => '16:00',
        'end_time' => '17:00',
        'planned_content' => 'تسميع الجزء الأول',
    ];
});

// إنشاء الحصص أصبح للأدمن فقط بقرار صاحب الموقع — المدير العام لم يعد ينشئ
test('session plan creation works for admin only', function () {
    $this->actingAs($this->admin)->post(route('group-session-plans.store'), array_merge($this->planPayload, [
        'circle_id' => $this->circle->id,
    ]))->assertRedirect();

    $gm = User::factory()->create(['status' => 'active']);
    $gm->assignRole('general_manager');
    $this->actingAs($gm)->post(route('group-session-plans.store'), array_merge($this->planPayload, [
        'circle_id' => $this->foreignCircle->id, 'session_name' => 'جلسة المدير العام',
    ]))->assertRedirect();

    expect(GroupSessionPlan::withoutGlobalScopes()->count())->toBe(1);
});

test('session plans index lists the plans and filters by circle', function () {
    GroupSessionPlan::create(array_merge($this->planPayload, ['circle_id' => $this->circle->id, 'session_name' => 'جلسة الحلقة الأولى']));
    GroupSessionPlan::create(array_merge($this->planPayload, ['circle_id' => $this->foreignCircle->id, 'session_name' => 'جلسة الحلقة الثانية']));

    $index = $this->actingAs($this->admin)->get(route('group-session-plans.index'));
    $index->assertOk();
    $index->assertSee('جلسة الحلقة الأولى');
    $index->assertSee('جلسة الحلقة الثانية');
    $index->assertSee('حلقة أولى');

    // التصفية بحلقة واحدة
    $filtered = $this->actingAs($this->admin)->get(route('group-session-plans.index', ['circle_id' => $this->circle->id]));
    $filtered->assertSee('جلسة الحلقة الأولى');
    $filtered->assertDontSee('جلسة الحلقة الثانية');
});

test('lesson weekly assessments relation works', function () {
    $lesson = EducationalLesson::create(['center_id' => $this->center->id, 'title' => 'درس الأخلاق', 'created_by' => $this->admin->id]);
    $student = Student::create([
        'name' => 'طالب', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $this->circle->id, 'center_id' => $this->center->id,
    ]);
    $followup = StudentWeeklyFollowup::create([
        'plan_type' => 'individual', 'center_id' => $this->center->id, 'circle_id' => $this->circle->id,
        'student_id' => $student->id, 'teacher_id' => $this->teacher->id,
        'week_start' => now()->startOfWeek()->format('Y-m-d'), 'week_end' => now()->endOfWeek()->format('Y-m-d'),
        'study_days' => ['Saturday'],
    ]);
    StudentWeeklyEducationalLesson::create([
        'weekly_followup_id' => $followup->id, 'educational_lesson_id' => $lesson->id,
        'level' => 'جيد', 'notes' => 'ملاحظة',
    ]);

    expect($lesson->weeklyAssessments()->count())->toBe(1)
        ->and($lesson->weeklyAssessments->first()->level)->toBe(\App\Enums\AssessmentLevel::Good);
});

test('lesson lifecycle as admin, used lesson cannot be deleted', function () {
    $this->actingAs($this->admin)->post(route('centers.educational-lessons.store', $this->center), [
        'title' => 'درس الصدق', 'description' => 'وصف',
    ])->assertRedirect(route('centers.show', $this->center));
    $lesson = EducationalLesson::withoutGlobalScopes()->first();
    expect($lesson)->not->toBeNull();

    $this->actingAs($this->admin)->put(route('educational-lessons.update', $lesson), [
        'title' => 'درس الصدق والأمانة', 'description' => 'وصف محدث',
    ])->assertRedirect();
    expect($lesson->fresh()->title)->toBe('درس الصدق والأمانة');

    // درس غير مستخدم: يُحذف
    $unused = EducationalLesson::create(['center_id' => $this->center->id, 'title' => 'درس مؤقت', 'created_by' => $this->admin->id]);
    $this->actingAs($this->admin)->delete(route('educational-lessons.destroy', $unused))->assertRedirect();
    expect(EducationalLesson::withoutGlobalScopes()->find($unused->id))->toBeNull();

    // درس مستخدم في متابعة: يُرفض الحذف ويبقى الدرس
    $student = Student::create([
        'name' => 'طالب', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $this->circle->id, 'center_id' => $this->center->id,
    ]);
    $followup = StudentWeeklyFollowup::create([
        'plan_type' => 'individual', 'center_id' => $this->center->id, 'circle_id' => $this->circle->id,
        'student_id' => $student->id, 'teacher_id' => $this->teacher->id,
        'week_start' => now()->startOfWeek()->format('Y-m-d'), 'week_end' => now()->endOfWeek()->format('Y-m-d'),
        'study_days' => ['Saturday'],
    ]);
    StudentWeeklyEducationalLesson::create([
        'weekly_followup_id' => $followup->id, 'educational_lesson_id' => $lesson->id, 'level' => 'جيد',
    ]);
    $this->actingAs($this->admin)->delete(route('educational-lessons.destroy', $lesson))
        ->assertRedirect(route('centers.show', $this->center))
        ->assertSessionHas('error');
    expect(EducationalLesson::withoutGlobalScopes()->find($lesson->id))->not->toBeNull();
});

test('teacher sees the lesson assessment on own followup page', function () {
    $lesson = EducationalLesson::create(['center_id' => $this->center->id, 'title' => 'درس البر', 'created_by' => $this->admin->id]);
    $student = Student::create([
        'name' => 'طالب المعلم', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $this->circle->id, 'center_id' => $this->center->id,
    ]);
    $followup = StudentWeeklyFollowup::create([
        'plan_type' => 'individual', 'center_id' => $this->center->id, 'circle_id' => $this->circle->id,
        'student_id' => $student->id, 'teacher_id' => $this->teacher->id,
        'week_start' => now()->startOfWeek()->format('Y-m-d'), 'week_end' => now()->endOfWeek()->format('Y-m-d'),
        'study_days' => ['Saturday'],
    ]);
    StudentWeeklyEducationalLesson::create([
        'weekly_followup_id' => $followup->id, 'educational_lesson_id' => $lesson->id,
        'level' => 'ممتاز', 'notes' => 'تفاعل رائع',
    ]);

    $page = $this->actingAs($this->teacherUser)->get(route('student-weekly-followups.show', $followup));
    $page->assertOk();
    $page->assertSee('درس البر');
});

test('supervisor cannot create or move session plans outside supervised branches', function () {
    $supUser = User::factory()->create(['status' => 'active']);
    $supUser->assignRole('supervisor');
    $supTeacher = Teacher::create(['user_id' => $supUser->id, 'branch_id' => $this->branch->id]);
    $this->branch->supervisors()->syncWithoutDetaching([$supTeacher->id]);

    // حلقة غريبة (فرع آخر): تُرفض ولا يُحفظ شيء
    $denied = $this->actingAs($supUser)->post(route('group-session-plans.store'), array_merge($this->planPayload, [
        'circle_id' => $this->foreignCircle->id,
    ]));
    $denied->assertRedirect();
    $denied->assertSessionMissing('errors');
    expect(GroupSessionPlan::withoutGlobalScopes()->count())->toBe(0);

    // وحلقة فرعه أيضًا: الإنشاء أصبح للأدمن فقط بقرار صاحب الموقع
    $this->actingAs($supUser)->post(route('group-session-plans.store'), array_merge($this->planPayload, [
        'circle_id' => $this->circle->id,
    ]))->assertRedirect();
    expect(GroupSessionPlan::withoutGlobalScopes()->count())->toBe(0);

    // نقل خطة موجودة لحلقة غريبة عبر التعديل: يُرفض وتبقى مكانها
    $plan = GroupSessionPlan::create(array_merge($this->planPayload, ['circle_id' => $this->circle->id]));
    $move = $this->actingAs($supUser)->put(route('group-session-plans.update', $plan), array_merge($this->planPayload, [
        'circle_id' => $this->foreignCircle->id,
    ]));
    $move->assertRedirect();
    expect($plan->fresh()->circle_id)->toBe($this->circle->id);
});

test('general manager can update a session plan and move it between circles', function () {
    $plan = GroupSessionPlan::create(array_merge($this->planPayload, ['circle_id' => $this->circle->id]));
    $gm = User::factory()->create(['status' => 'active']);
    $gm->assignRole('general_manager');

    $this->actingAs($gm)->put(route('group-session-plans.update', $plan), array_merge($this->planPayload, [
        'circle_id' => $this->foreignCircle->id, 'session_name' => 'جلسة منقولة',
    ]))->assertRedirect();
    expect($plan->fresh()->circle_id)->toBe($this->foreignCircle->id)
        ->and($plan->fresh()->session_name)->toBe('جلسة منقولة');
});
