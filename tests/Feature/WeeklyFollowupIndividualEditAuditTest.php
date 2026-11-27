<?php

/**
 * فحص تعديل المتابعة الأسبوعية الفردية (REPORT 230):
 * طلب التعديل كان أضعف من طلب الإنشاء الذي أُصلح في 227 — المعلم بـ exists
 * فقط، ونقل المتابعة لطالب آخر بلا فحوص الطالب في الإنشاء، وصفحة
 * التعديل/العرض تنهاران 500 لمتابعة بلا صفوف أقسام.
 */

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\StudentWeeklyFollowup;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RecommendationTemplateSeeder;
use Database\Seeders\SurahSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

function weiUser(string $role, string $email): User
{
    $u = User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('Test123!'), 'status' => 'active']);
    $u->assignRole($role);
    return $u;
}

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SurahSeeder::class);
    $this->seed(RecommendationTemplateSeeder::class);

    $this->centerA = Center::create(['name' => 'مركز أ']);
    $this->centerB = Center::create(['name' => 'مركز ب']);
    $this->branchA = Branch::create(['name' => 'فرع أ', 'center_id' => $this->centerA->id]);
    $this->branchB = Branch::create(['name' => 'فرع ب', 'center_id' => $this->centerB->id]);
    $this->grpA = Circle::create(['name' => 'جماعية أ', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchA->id]);
    $this->indA = Circle::create(['name' => 'فردية أ', 'type' => 'individual', 'level' => 'build', 'branch_id' => $this->branchA->id]);
    $this->indA2 = Circle::create(['name' => 'فردية أ2', 'type' => 'individual', 'level' => 'build', 'branch_id' => $this->branchA->id]);

    $this->teacherUserA = weiUser('teacher', 'ta@wei.local');
    $this->teacherA = Teacher::create(['user_id' => $this->teacherUserA->id, 'branch_id' => $this->branchA->id]);
    DB::table('circle_teacher')->insert([
        ['circle_id' => $this->grpA->id, 'teacher_id' => $this->teacherA->id, 'role' => 'main'],
        ['circle_id' => $this->indA->id, 'teacher_id' => $this->teacherA->id, 'role' => 'main'],
        ['circle_id' => $this->indA2->id, 'teacher_id' => $this->teacherA->id, 'role' => 'main'],
    ]);

    $this->teacherUserA2 = weiUser('teacher', 'ta2@wei.local');
    $this->teacherA2 = Teacher::create(['user_id' => $this->teacherUserA2->id, 'branch_id' => $this->branchA->id]);

    $this->teacherUserB = weiUser('teacher', 'tb@wei.local');
    $this->teacherB = Teacher::create(['user_id' => $this->teacherUserB->id, 'branch_id' => $this->branchB->id]);

    $this->supUserA = weiUser('supervisor', 'sup@wei.local');
    $this->supTeacherA = Teacher::create(['user_id' => $this->supUserA->id, 'branch_id' => $this->branchA->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branchA->id, 'teacher_id' => $this->supTeacherA->id]);

    $this->admin = weiUser('admin', 'admin@wei.local');

    $mk = fn ($name, $center, $circle) => Student::create([
        'name' => $name, 'center_id' => $center->id, 'circle_id' => $circle?->id,
        'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر',
    ]);
    $this->studentIndA = $mk('طالب فردية أ', $this->centerA, $this->indA);
    $this->studentIndA2 = $mk('طالب فردية أ2', $this->centerA, $this->indA2);
    $this->studentGrpA = $mk('طالب جماعية أ', $this->centerA, $this->grpA);
    $this->studentNoCircleB = $mk('طالب بلا حلقة ب', $this->centerB, null);

    $this->weekStart = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->format('Y-m-d');
    $this->weekEnd = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->addDays(4)->format('Y-m-d');
    $this->week2Start = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->addWeek()->format('Y-m-d');
    $this->week2End = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->addWeek()->addDays(4)->format('Y-m-d');

    $this->followup = StudentWeeklyFollowup::create([
        'batch_id' => null, 'plan_type' => 'individual',
        'center_id' => $this->centerA->id, 'circle_id' => $this->indA->id,
        'student_id' => $this->studentIndA->id, 'teacher_id' => $this->teacherA->id,
        'week_start' => $this->weekStart, 'week_end' => $this->weekEnd, 'study_days' => [],
    ]);
});

function weiPayload(object $t, array $override = []): array
{
    return array_merge([
        'circle_id' => $t->indA->id, 'student_id' => $t->studentIndA->id, 'teacher_id' => $t->teacherA->id,
        'week_start' => $t->weekStart, 'week_end' => $t->weekEnd,
    ], $override);
}

test('individual edit: show and edit pages load for a followup whose plan sections are empty', function () {
    // إنشاء عبر التدفق الحقيقي بحمولة دنيا (كل أقسام الخطة اختيارية)
    $this->actingAs($this->teacherUserA)->post(route('student-weekly-followups.store-individual'), weiPayload($this, [
        'student_id' => $this->studentIndA2->id, 'circle_id' => $this->indA2->id,
        'week_start' => $this->week2Start, 'week_end' => $this->week2End,
    ]))->assertRedirect();
    $created = StudentWeeklyFollowup::withoutGlobalScopes()->where('student_id', $this->studentIndA2->id)->first();
    expect($created)->not->toBeNull();

    $this->actingAs($this->teacherUserA)->get(route('student-weekly-followups.edit-individual', $created))->assertOk();
    $this->actingAs($this->teacherUserA)->get(route('student-weekly-followups.show-individual', $created))->assertOk();
    expect($this->actingAs($this->teacherUserA2)->get(route('student-weekly-followups.edit-individual', $created))->status())->not->toBe(200);
});

test('individual edit: forging a foreign teacher is rejected, for supervisors and teachers alike', function () {
    $this->actingAs($this->supUserA)->put(route('student-weekly-followups.update-individual', $this->followup), weiPayload($this, ['teacher_id' => $this->teacherB->id]))
        ->assertSessionHasErrors('teacher_id');
    $this->actingAs($this->teacherUserA)->put(route('student-weekly-followups.update-individual', $this->followup), weiPayload($this, ['teacher_id' => $this->teacherA2->id]))
        ->assertSessionHasErrors('teacher_id');
    expect($this->followup->fresh()->teacher_id)->toBe($this->teacherA->id);
});

test('individual edit: moving the followup to a foreign circle-less student is rejected', function () {
    $p = weiPayload($this, ['student_id' => $this->studentNoCircleB->id]);
    unset($p['circle_id']);
    $this->actingAs($this->teacherUserA)->put(route('student-weekly-followups.update-individual', $this->followup), $p);
    expect($this->followup->fresh()->student_id)->toBe($this->studentIndA->id);
});

test('individual edit: moving the followup to a group-circle student is rejected with an explicit message', function () {
    $this->actingAs($this->teacherUserA)->put(route('student-weekly-followups.update-individual', $this->followup), weiPayload($this, [
        'student_id' => $this->studentGrpA->id, 'circle_id' => $this->grpA->id,
    ]));
    expect(session('error'))->not->toBeNull();
    expect($this->followup->fresh()->student_id)->toBe($this->studentIndA->id);
});

test('individual edit: a payload circle different from the student circle is rejected', function () {
    $this->actingAs($this->teacherUserA)->put(route('student-weekly-followups.update-individual', $this->followup), weiPayload($this, [
        'circle_id' => $this->indA2->id,
    ]));
    expect(session('error'))->not->toBeNull();
    expect($this->followup->fresh()->circle_id)->toBe($this->indA->id);
});

test('individual edit: duplicate collision on student+week gives a validation message and no move', function () {
    StudentWeeklyFollowup::create([
        'batch_id' => null, 'plan_type' => 'individual',
        'center_id' => $this->centerA->id, 'circle_id' => $this->indA2->id,
        'student_id' => $this->studentIndA2->id, 'teacher_id' => $this->teacherA->id,
        'week_start' => $this->week2Start, 'week_end' => $this->week2End, 'study_days' => [],
    ]);
    $this->actingAs($this->teacherUserA)->put(route('student-weekly-followups.update-individual', $this->followup), weiPayload($this, [
        'student_id' => $this->studentIndA2->id, 'circle_id' => $this->indA2->id,
        'week_start' => $this->week2Start, 'week_end' => $this->week2End,
    ]))->assertSessionHasErrors('student_id');
    expect($this->followup->fresh()->student_id)->toBe($this->studentIndA->id);
});

test('individual edit: omitting required keys yields validation errors, never a 500', function () {
    foreach (['student_id', 'teacher_id', 'week_start'] as $key) {
        $p = weiPayload($this);
        unset($p[$key]);
        expect($this->actingAs($this->teacherUserA)->put(route('student-weekly-followups.update-individual', $this->followup), $p)->status())->not->toBe(500);
    }
});

test('individual edit: a group record is not editable through the individual routes', function () {
    $group = StudentWeeklyFollowup::create([
        'batch_id' => (string) Str::uuid(), 'plan_type' => 'group',
        'center_id' => $this->centerA->id, 'circle_id' => $this->grpA->id,
        'student_id' => $this->studentGrpA->id, 'teacher_id' => $this->teacherA->id,
        'week_start' => $this->weekStart, 'week_end' => $this->weekEnd, 'study_days' => [],
    ]);
    $this->actingAs($this->admin)->get(route('student-weekly-followups.edit-individual', $group))->assertNotFound();
    $this->actingAs($this->admin)->put(route('student-weekly-followups.update-individual', $group), weiPayload($this, [
        'student_id' => $this->studentGrpA->id, 'circle_id' => $this->grpA->id,
        'week_start' => $this->week2Start, 'week_end' => $this->week2End,
    ]));
    expect($group->fresh()->week_start->format('Y-m-d'))->toBe($this->weekStart);
    // ولا سجل فردي يُفتح عبر مسار تعديل الجماعية بمعرّفه
    $this->actingAs($this->admin)->put(route('student-weekly-followups.update-group', (string) $this->followup->id), [])->assertNotFound();
});

test('individual edit: a non-standard week date is rejected by the strict format', function () {
    $this->actingAs($this->teacherUserA)->put(route('student-weekly-followups.update-individual', $this->followup), weiPayload($this, ['week_start' => '2026/11/20']))
        ->assertSessionHasErrors('week_start');
});

/**
 * توثيق سلوك قائم مرتبط بنقطة قرار 227 المفتوحة: فورم الفردية لا يفلتر
 * بالحالة، فإعادة حفظ متابعة طالب صار متوقفًا تنجح — لا يُغيَّر هنا.
 */
test('individual edit: re-saving a followup whose student stopped still succeeds (open decision from 227)', function () {
    $this->studentIndA->update(['status' => 'متوقف']);
    $this->actingAs($this->teacherUserA)->put(route('student-weekly-followups.update-individual', $this->followup), weiPayload($this, ['notes' => 'ملحوظة']))
        ->assertRedirect(route('student-weekly-followups.index-individual'));
});
