<?php

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\EducationalLesson;
use App\Models\GroupSessionPlan;
use App\Models\Student;
use App\Models\StudentConstructionDetail;
use App\Models\StudentWeeklyFollowup;
use App\Models\StudentWeeklyNewMemorization;
use App\Models\Surah;
use App\Models\Subscription;
use App\Models\SubscriptionPrice;
use App\Models\TafsirFile;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/*
|--------------------------------------------------------------------------
| انحدار اكتشافات زحف الصفحات (نوفمبر 2026)
|--------------------------------------------------------------------------
| صفحات كانت تنهار بـ 500 عند فتحها بالأدوار المصرّح لها:
| - عرض/تعديل متابعة أسبوعية (اسم راوت غير موجود + first() على علاقة HasOne)
| - عرض خطة جلسة جماعية (متغير $session غير معرّف + دالة وصول غير موجودة)
| - عرض خطة بناء طالب (ملف view مفقود)
| - صفحة تعديل درس تعليمي يتيمة وصفحة عرض ملف تفسير غير موجودة (500 بدل 404)
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->center = Center::create(['name' => 'مركز الزحف']);
    $this->branch = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع الزحف']);
    $this->circle = Circle::create(['branch_id' => $this->branch->id, 'name' => 'حلقة الزحف', 'type' => 'بنين', 'level' => 'مبتدئ']);
    $this->student = Student::create([
        'name' => 'طالب الزحف', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $this->circle->id, 'center_id' => $this->center->id,
    ]);

    $mk = function (string $role) {
        $u = User::factory()->create(['status' => 'active']);
        $u->assignRole($role);
        return $u;
    };
    $this->admin = $mk('admin');

    $this->supervisor = $mk('supervisor');
    $supT = Teacher::create(['user_id' => $this->supervisor->id]);
    $supT->branch()->associate($this->branch)->save();
    $this->branch->supervisors()->attach($supT->id);

    $this->manager = $mk('manager');
    $mgrT = Teacher::create(['user_id' => $this->manager->id]);
    $mgrT->branch()->associate($this->branch)->save();

    $this->teacherUser = $mk('teacher');
    $this->teacher = Teacher::create(['user_id' => $this->teacherUser->id]);
    $this->teacher->branch()->associate($this->branch)->save();
    $this->teacher->circles()->attach($this->circle->id, ['role' => 'main']);

    $mkFollowup = fn (array $extra = []) => StudentWeeklyFollowup::create(array_merge([
        'plan_type' => 'individual', 'center_id' => $this->center->id, 'circle_id' => $this->circle->id,
        'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id,
        'week_start' => now()->startOfWeek()->format('Y-m-d'), 'week_end' => now()->endOfWeek()->format('Y-m-d'),
        'study_days' => ['السبت', 'الأحد'],
    ], $extra));
    $this->mkFollowup = $mkFollowup;
});

test('subscription edit page opens for supervisor and teacher', function () {
    $price = SubscriptionPrice::create(['circle_level' => 'مبتدئ', 'education_stage' => 'ابتدائي', 'amount' => 100]);
    $sub = Subscription::create([
        'student_id' => $this->student->id, 'circle_id' => $this->circle->id,
        'subscription_price_id' => $price->id, 'month' => '2026-11-01', 'amount' => 100, 'status' => 'مدفوع',
    ]);

    $this->actingAs($this->admin)->get("/subscriptions/{$sub->id}/edit")->assertOk();
    $this->actingAs($this->supervisor)->get("/subscriptions/{$sub->id}/edit")->assertOk();
    $this->actingAs($this->teacherUser)->get("/subscriptions/{$sub->id}/edit")->assertOk();
});

test('weekly followup show and edit open without has-one rows', function () {
    $followup = ($this->mkFollowup)();

    $this->actingAs($this->admin)->get("/student-weekly-followups/{$followup->id}")->assertOk();
    $this->actingAs($this->admin)->get("/student-weekly-followups/{$followup->id}/edit")->assertOk();
});

test('weekly followup edit shows its own memorization data only', function () {
    $this->seed(\Database\Seeders\SurahSeeder::class);
    $mem = fn ($followupId, $note) => StudentWeeklyNewMemorization::create([
        'weekly_followup_id' => $followupId, 'notes' => $note,
        'plan_from_surah_id' => Surah::find(1)->id, 'plan_from_ayah' => 1,
        'plan_to_surah_id' => Surah::find(2)->id, 'plan_to_ayah' => 5,
    ]);
    $first = ($this->mkFollowup)();
    $mem($first->id, 'ملحوظة المتابعة الأولى');
    $student2 = Student::create([
        'name' => 'طالب آخر', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $this->circle->id, 'center_id' => $this->center->id,
    ]);
    $second = ($this->mkFollowup)(['student_id' => $student2->id]);
    $mem($second->id, 'ملحوظة المتابعة الثانية');

    $response = $this->actingAs($this->admin)->get("/student-weekly-followups/{$second->id}/edit");
    $response->assertOk();
    $response->assertSee('ملحوظة المتابعة الثانية');
    $response->assertDontSee('ملحوظة المتابعة الأولى');
});

test('group weekly followup show and edit open', function () {
    ($this->mkFollowup)(['batch_id' => 'batch-test-1', 'plan_type' => 'group']);
    $student2 = Student::create([
        'name' => 'طالب آخر', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $this->circle->id, 'center_id' => $this->center->id,
    ]);
    ($this->mkFollowup)(['batch_id' => 'batch-test-1', 'plan_type' => 'group', 'student_id' => $student2->id]);

    $this->actingAs($this->admin)->get('/student-weekly-followups/group/batch-test-1')->assertOk();
    $this->actingAs($this->admin)->get('/student-weekly-followups/group/batch-test-1/edit')->assertOk();
});

test('group session plan show opens for admin and supervisor, edit for manager', function () {
    $plan = GroupSessionPlan::create([
        'circle_id' => $this->circle->id, 'session_name' => 'جلسة الزحف',
        'start_time' => '16:00', 'end_time' => '17:00',
    ]);

    $this->actingAs($this->admin)->get("/group-session-plans/{$plan->id}")->assertOk();
    $this->actingAs($this->supervisor)->get("/group-session-plans/{$plan->id}")->assertOk();
    $this->actingAs($this->manager)->get("/group-session-plans/{$plan->id}/edit")->assertOk();
});

test('student construction detail show opens for admin', function () {
    $detail = StudentConstructionDetail::create([
        'student_id' => $this->student->id, 'circle_id' => $this->circle->id, 'study_system' => 'نظام الزحف',
    ]);

    $this->actingAs($this->admin)->get("/student-construction-details/{$detail->id}")->assertOk();
});

test('orphan educational lesson edit page is 404 not 500', function () {
    $lesson = EducationalLesson::create(['center_id' => $this->center->id, 'title' => 'درس الزحف', 'created_by' => $this->admin->id]);

    $this->actingAs($this->admin)->get("/educational-lessons/{$lesson->id}/edit")->assertNotFound();
    $this->actingAs($this->admin)->get("/centers/{$this->center->id}")->assertOk();
});

test('tafsir file show is not a 500 and index still opens', function () {
    $file = TafsirFile::create(['name' => 'ملف الزحف']);

    // لا توجد صفحة عرض لملفات التفسير: الرابط يرد 405 (الراوت موجود للـ PUT/DELETE فقط) بدل الانهيار بـ 500
    $this->actingAs($this->admin)->get("/tafsir-files/{$file->id}")->assertStatus(405);
    $this->actingAs($this->admin)->get('/tafsir-files')->assertOk();
});

test('subscription edit denies cleanly when student is out of scope', function () {
    // اكتشاف الزحف الحي: اشتراك حلقته داخل نطاق المستخدم لكن طالبه في حلقة خارج النطاق —
    // نطاق CenterScope يجعل علاقة الطالب null فكانت البوليسي تنهار بـ 500 بدل رفض نظيف
    $branchB = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع آخر']);
    $circleB = Circle::create(['branch_id' => $branchB->id, 'name' => 'حلقة خارجية', 'type' => 'بنين', 'level' => 'مبتدئ']);
    $studentOut = Student::create([
        'name' => 'طالب خارج النطاق', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $circleB->id, 'center_id' => $this->center->id,
    ]);
    $price = SubscriptionPrice::create(['circle_level' => 'مبتدئ', 'education_stage' => 'ابتدائي', 'amount' => 100]);
    $sub = Subscription::create([
        'student_id' => $studentOut->id, 'circle_id' => $this->circle->id,
        'subscription_price_id' => $price->id, 'month' => '2026-11-01', 'amount' => 100, 'status' => 'مدفوع',
    ]);

    // الرفض النظيف في هذا التطبيق = 403 أو تحويل ودي 302 من معالج الأخطاء — المهم: لا 500
    foreach ([$this->teacherUser, $this->supervisor] as $actor) {
        $status = $this->actingAs($actor)->get("/subscriptions/{$sub->id}/edit")->status();
        $this->assertContains($status, [302, 403], 'Subscription edit must deny cleanly, not crash');
    }
});

test('attendance edit denies cleanly when student is out of scope', function () {
    // نفس نمط الانهيار في AttendancePolicy: سجل حضور حلقته داخل النطاق وطالبه خارجه
    $branchB = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع آخر']);
    $circleB = Circle::create(['branch_id' => $branchB->id, 'name' => 'حلقة خارجية', 'type' => 'بنين', 'level' => 'مبتدئ']);
    $studentOut = Student::create([
        'name' => 'طالب خارج النطاق', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $circleB->id, 'center_id' => $this->center->id,
    ]);
    $attendance = \App\Models\Attendance::create([
        'student_id' => $studentOut->id, 'circle_id' => $this->circle->id, 'teacher_id' => $this->teacher->id,
        'date' => now()->format('Y-m-d'), 'status' => 'present',
    ]);

    // السجل نفسه مخفي عن المعلم بنطاق CenterScope فيرد الربط 404 قبل الوصول للبوليسي — رفض نظيف بلا 500
    $status = $this->actingAs($this->teacherUser)->get("/attendance/{$attendance->id}/edit")->status();
    $this->assertContains($status, [302, 403, 404], 'Attendance edit must deny cleanly, not crash');
});
