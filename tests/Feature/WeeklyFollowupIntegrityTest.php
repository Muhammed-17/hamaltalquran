<?php

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\EducationalLesson;
use App\Models\Recommendation;
use App\Models\Student;
use App\Models\StudentActivity;
use App\Models\StudentWeeklyEducationalLesson;
use App\Models\StudentWeeklyFollowup;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RecommendationTemplateSeeder;
use Database\Seeders\SurahSeeder;

/*
|--------------------------------------------------------------------------
| سلامة نظام المتابعات الأسبوعية (نوفمبر 2026)
|--------------------------------------------------------------------------
| - تعديل الصف الواحد كان يمحو مستوى وملاحظات تقييم الدرس التربوي المحفوظة.
| - تكرار متابعة فردية (طالب + أسبوع) كان يصل لقيد قاعدة البيانات ويعرض
|   خطأ SQL خامًا — الآن تحقق ودي في طلبي الإنشاء والتعديل الفردي.
| - مركز المتابعة الجماعية يُشتق من الحلقة المختارة نفسها لا من أول حلقة متاحة.
| - الإنشاء مقصور على حلقات نطاق وصول المستخدم (حارس في الكنترولر).
| - مقارنة الأسبوع بـ whereDate (تتوافق مع MySQL وSQLite معًا).
*/
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SurahSeeder::class);
    $this->seed(RecommendationTemplateSeeder::class);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');

    $this->center = Center::create(['name' => 'مركز الفحص']);
    $this->branch = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع الفحص']);
    $this->circle = Circle::create(['branch_id' => $this->branch->id, 'name' => 'حلقة الفحص', 'type' => 'بنين', 'level' => 'مبتدئ']);
    $teacherUser = User::factory()->create(['status' => 'active']);
    $this->teacher = Teacher::create(['user_id' => $teacherUser->id, 'branch_id' => $this->branch->id]);
    $this->student = Student::create([
        'name' => 'طالب الفحص', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $this->circle->id, 'center_id' => $this->center->id,
    ]);
    $this->weekStart = now()->startOfWeek()->format('Y-m-d');
    $this->weekEnd = now()->endOfWeek()->format('Y-m-d');
});

test('single-row edit preserves the educational lesson assessment', function () {
    $followup = StudentWeeklyFollowup::create([
        'plan_type' => 'individual', 'center_id' => $this->center->id, 'circle_id' => $this->circle->id,
        'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id,
        'week_start' => $this->weekStart, 'week_end' => $this->weekEnd, 'study_days' => ['Saturday'],
    ]);
    $lesson = EducationalLesson::create(['center_id' => $this->center->id, 'title' => 'درس', 'created_by' => $this->admin->id]);
    StudentWeeklyEducationalLesson::create([
        'weekly_followup_id' => $followup->id, 'educational_lesson_id' => $lesson->id,
        'level' => 'جيد', 'notes' => 'ملاحظة الدرس الأصلية',
    ]);

    // تعديل صف واحد من فورم edit_single (لا يحتوي حقول درس تربوي)
    $this->actingAs($this->admin)->put(route('student-weekly-followups.update', $followup), [
        'notes' => 'تعديل بسيط',
        'discipline_level' => 'ممتاز',
    ])->assertRedirect();

    $assessment = StudentWeeklyEducationalLesson::where('weekly_followup_id', $followup->id)->first();
    expect($assessment)->not->toBeNull()
        ->and($assessment->level)->toBe(\App\Enums\AssessmentLevel::Good)
        ->and($assessment->notes)->toBe('ملاحظة الدرس الأصلية');
});

test('duplicate individual followup gets friendly validation instead of a raw SQL error', function () {
    $payload = [
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'circle_id' => $this->circle->id,
        'week_start' => $this->weekStart,
        'week_end' => $this->weekEnd,
        'study_days' => ['Saturday'],
        'discipline_level' => 'جيد',
    ];

    $this->actingAs($this->admin)->post(route('student-weekly-followups.store-individual'), $payload)
        ->assertRedirect(route('student-weekly-followups.index-individual'));

    $second = $this->actingAs($this->admin)->post(route('student-weekly-followups.store-individual'), $payload);
    $second->assertSessionHasErrors('student_id');
    expect(session('error'))->toBeNull();
    expect(StudentWeeklyFollowup::count())->toBe(1);
});

test('deleting a followup removes all related rows', function () {
    $payload = [
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'circle_id' => $this->circle->id,
        'week_start' => $this->weekStart,
        'week_end' => $this->weekEnd,
        'study_days' => ['Saturday'],
        'discipline_level' => 'جيد',
        'activities' => [[
            'activity_type' => 'نشاط', 'activity_name' => 'مسابقة',
            'activity_date' => $this->weekStart, 'notes' => null,
        ]],
    ];
    $this->actingAs($this->admin)->post(route('student-weekly-followups.store-individual'), $payload)
        ->assertRedirect(route('student-weekly-followups.index-individual'));

    $followup = StudentWeeklyFollowup::firstOrFail();
    expect(Recommendation::where('weekly_followup_id', $followup->id)->count())->toBe(1)
        ->and(StudentActivity::where('weekly_followup_id', $followup->id)->count())->toBe(1);

    $this->actingAs($this->admin)->delete(route('student-weekly-followups.destroy', $followup))
        ->assertRedirect(route('student-weekly-followups.index-individual', ['student_id' => $this->student->id]));

    expect(StudentWeeklyFollowup::count())->toBe(0)
        ->and(Recommendation::where('weekly_followup_id', $followup->id)->count())->toBe(0)
        ->and(StudentActivity::where('weekly_followup_id', $followup->id)->count())->toBe(0)
        ->and(\App\Models\StudentWeeklyDiscipline::where('weekly_followup_id', $followup->id)->count())->toBe(0);
});

test('group batch lifecycle: create, update removing a student, delete', function () {
    $groupCircle = Circle::create(['branch_id' => $this->branch->id, 'name' => 'حلقة جماعية', 'type' => 'group', 'level' => 'مبتدئ']);
    $this->student->update(['circle_id' => $groupCircle->id]);
    $student2 = Student::create([
        'name' => 'طالب ثانٍ', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $groupCircle->id, 'center_id' => $this->center->id,
    ]);

    $payload = [
        'circle_id' => $groupCircle->id,
        'teacher_id' => $this->teacher->id,
        'week_start' => $this->weekStart,
        'week_end' => $this->weekEnd,
        'study_days' => ['Saturday', 'Sunday'],
        'students' => [
            ['student_id' => $this->student->id, 'discipline_level' => 'جيد'],
            ['student_id' => $student2->id, 'discipline_level' => 'مقبول'],
        ],
    ];

    $create = $this->actingAs($this->admin)->post(route('student-weekly-followups.store-group'), $payload);
    $create->assertRedirect();
    expect(StudentWeeklyFollowup::count())->toBe(2);
    $batchId = StudentWeeklyFollowup::first()->batch_id;
    expect(StudentWeeklyFollowup::where('batch_id', $batchId)->count())->toBe(2)
        ->and(Recommendation::count())->toBe(2);

    // تحديث: إزالة الطالب الثاني وتعديل مستوى الأول
    $payload['students'] = [
        ['student_id' => $this->student->id, 'discipline_level' => 'ممتاز'],
    ];
    $this->actingAs($this->admin)->put(route('student-weekly-followups.update-group', $batchId), $payload)
        ->assertRedirect(route('student-weekly-followups.index-group'));

    expect(StudentWeeklyFollowup::count())->toBe(1)
        ->and(StudentWeeklyFollowup::first()->student_id)->toBe($this->student->id)
        ->and(Recommendation::count())->toBe(1);

    $this->actingAs($this->admin)->delete(route('student-weekly-followups.destroy-group', $batchId))
        ->assertRedirect(route('student-weekly-followups.index-group'));
    expect(StudentWeeklyFollowup::count())->toBe(0)
        ->and(Recommendation::count())->toBe(0);
});

test('group followup center is derived from the selected circle', function () {
    // حلقة جماعية في المركز الأول تُنشأ أولًا (أي fallback قديم كان سيختار مركزها)
    Circle::create(['branch_id' => $this->branch->id, 'name' => 'حلقة المركز الأول', 'type' => 'group', 'level' => 'مبتدئ']);
    // مركز ثانٍ بحلقة جماعية خاصة به
    $center2 = Center::create(['name' => 'المركز الثاني']);
    $branch2 = Branch::create(['center_id' => $center2->id, 'name' => 'فرع المركز الثاني']);
    $circle2 = Circle::create(['branch_id' => $branch2->id, 'name' => 'حلقة المركز الثاني', 'type' => 'group', 'level' => 'مبتدئ']);
    $student = Student::create([
        'name' => 'طالب المركز الثاني', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $circle2->id, 'center_id' => $center2->id,
    ]);

    $this->actingAs($this->admin)->post(route('student-weekly-followups.store-group'), [
        'circle_id' => $circle2->id,
        'teacher_id' => $this->teacher->id,
        'week_start' => $this->weekStart,
        'week_end' => $this->weekEnd,
        'study_days' => ['Saturday'],
        'students' => [
            ['student_id' => $student->id, 'discipline_level' => 'جيد'],
        ],
    ])->assertRedirect();

    $followup = StudentWeeklyFollowup::first();
    expect($followup)->not->toBeNull()
        ->and($followup->center_id)->toBe($center2->id);
});

test('teacher cannot create or move followups outside their circles', function () {
    // معلم حقيقي مرتبط بحلقة جماعية في الفرع الأول
    $ownCircle = Circle::create(['branch_id' => $this->branch->id, 'name' => 'حلقة المعلم', 'type' => 'group', 'level' => 'مبتدئ']);
    $teacherUser = User::factory()->create(['status' => 'active']);
    $teacherUser->assignRole('teacher');
    $teacher = Teacher::create(['user_id' => $teacherUser->id, 'branch_id' => $this->branch->id]);
    \Illuminate\Support\Facades\DB::table('circle_teacher')->insert([
        'circle_id' => $ownCircle->id, 'teacher_id' => $teacher->id, 'role' => 'main',
    ]);
    $ownStudent = Student::create([
        'name' => 'طالب حلقة المعلم', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $ownCircle->id, 'center_id' => $this->center->id,
    ]);

    // حلقة غريبة في فرع/مركز آخر وطالبها
    $center2 = Center::create(['name' => 'مركز آخر']);
    $branch2 = Branch::create(['center_id' => $center2->id, 'name' => 'فرع آخر']);
    $foreignCircle = Circle::create(['branch_id' => $branch2->id, 'name' => 'حلقة غريبة', 'type' => 'group', 'level' => 'مبتدئ']);
    $foreignStudent = Student::create([
        'name' => 'طالب غريب', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $foreignCircle->id, 'center_id' => $center2->id,
    ]);

    $base = [
        'teacher_id' => $teacher->id,
        'week_start' => $this->weekStart,
        'week_end' => $this->weekEnd,
        'study_days' => ['Saturday'],
    ];

    // محاولة جماعية لحلقة غريبة: تُرفض ولا شيء يُحفظ — منذ فحص 226 صار الرفض
    // من تحقق طلب الإنشاء نفسه (قاعدة الحلقة المسموحة كطلب التعديل) لا من
    // معالج 403 في الكنترولر، فتظهر رسالة تحقق بدل التحويل الصامت
    $denied = $this->actingAs($teacherUser)->post(route('student-weekly-followups.store-group'), $base + [
        'circle_id' => $foreignCircle->id,
        'students' => [['student_id' => $foreignStudent->id, 'discipline_level' => 'جيد']],
    ]);
    $denied->assertRedirect();
    $denied->assertSessionHasErrors('circle_id');
    expect(StudentWeeklyFollowup::count())->toBe(0);

    // محاولة فردية لطالب غريب: تُرفض كذلك
    $denied2 = $this->actingAs($teacherUser)->post(route('student-weekly-followups.store-individual'), $base + [
        'student_id' => $foreignStudent->id,
        'circle_id' => $foreignCircle->id,
    ]);
    $denied2->assertRedirect();
    $denied2->assertSessionMissing('errors');
    expect(StudentWeeklyFollowup::count())->toBe(0);

    // حلقته هو: ينجح
    $this->actingAs($teacherUser)->post(route('student-weekly-followups.store-group'), $base + [
        'circle_id' => $ownCircle->id,
        'students' => [['student_id' => $ownStudent->id, 'discipline_level' => 'جيد']],
    ])->assertRedirect();
    expect(StudentWeeklyFollowup::count())->toBe(1);

    // محاولة نقل الدفعة إلى الحلقة الغريبة عبر التعديل: تُرفض وتبقى الدفعة مكانها
    $batchId = StudentWeeklyFollowup::first()->batch_id;
    $move = $this->actingAs($teacherUser)->put(route('student-weekly-followups.update-group', $batchId), $base + [
        'circle_id' => $foreignCircle->id,
        'students' => [['student_id' => $foreignStudent->id, 'discipline_level' => 'جيد']],
    ]);
    // الرفض هنا يأتي من تحقق الطلب نفسه (قاعدة الحلقة المسموحة) قبل الكنترولر
    $move->assertRedirect();
    expect(StudentWeeklyFollowup::first()->circle_id)->toBe($ownCircle->id);
});
