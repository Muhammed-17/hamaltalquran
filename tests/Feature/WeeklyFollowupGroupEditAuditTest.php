<?php

/**
 * فحص تعديل المتابعة الأسبوعية الجماعية (REPORT 229):
 * البوابات والنطاق والتكرار كانت سليمة، وخطأ واحد مؤكد: نقل الدفعة
 * لحلقة مركز آخر كان يُبقي center_id على مركز الدفعة الأصلي.
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

function wegUser(string $role, string $email): User
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
    $this->grpB = Circle::create(['name' => 'جماعية ب', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchB->id]);

    $this->teacherUserA = wegUser('teacher', 'ta@weg.local');
    $this->teacherA = Teacher::create(['user_id' => $this->teacherUserA->id, 'branch_id' => $this->branchA->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->grpA->id, 'teacher_id' => $this->teacherA->id, 'role' => 'main']);

    $this->teacherUserA2 = wegUser('teacher', 'ta2@weg.local');
    $this->teacherA2 = Teacher::create(['user_id' => $this->teacherUserA2->id, 'branch_id' => $this->branchA->id]);

    $this->teacherUserB = wegUser('teacher', 'tb@weg.local');
    $this->teacherB = Teacher::create(['user_id' => $this->teacherUserB->id, 'branch_id' => $this->branchB->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->grpB->id, 'teacher_id' => $this->teacherB->id, 'role' => 'main']);

    $this->supUserA = wegUser('supervisor', 'sup@weg.local');
    $this->supTeacherA = Teacher::create(['user_id' => $this->supUserA->id, 'branch_id' => $this->branchA->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branchA->id, 'teacher_id' => $this->supTeacherA->id]);

    $this->admin = wegUser('admin', 'admin@weg.local');

    $mk = fn ($name, $center, $circle) => Student::create([
        'name' => $name, 'center_id' => $center->id, 'circle_id' => $circle?->id,
        'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر',
    ]);
    $this->studentGrpA = $mk('طالب جماعية أ', $this->centerA, $this->grpA);
    $this->studentGrpB = $mk('طالب جماعية ب', $this->centerB, $this->grpB);

    $this->weekStart = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->format('Y-m-d');
    $this->weekEnd = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->addDays(4)->format('Y-m-d');
    $this->week2Start = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->addWeek()->format('Y-m-d');
    $this->week2End = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->addWeek()->addDays(4)->format('Y-m-d');

    $this->batchId = (string) Str::uuid();
    StudentWeeklyFollowup::create([
        'batch_id' => $this->batchId, 'plan_type' => 'group',
        'center_id' => $this->centerA->id, 'circle_id' => $this->grpA->id,
        'student_id' => $this->studentGrpA->id, 'teacher_id' => $this->teacherA->id,
        'week_start' => $this->weekStart, 'week_end' => $this->weekEnd, 'study_days' => [],
    ]);
});

function wegPayload(object $t, array $override = []): array
{
    return array_merge([
        'circle_id' => $t->grpA->id, 'teacher_id' => $t->teacherA->id,
        'week_start' => $t->weekStart, 'week_end' => $t->weekEnd,
        'students' => [['student_id' => $t->studentGrpA->id, 'discipline_level' => 'جيد']],
    ], $override);
}

test('group edit: page opens for the owner teacher and branch supervisor, not for another teacher', function () {
    $this->actingAs($this->teacherUserA)->get(route('student-weekly-followups.edit-group', $this->batchId))->assertOk();
    $this->actingAs($this->supUserA)->get(route('student-weekly-followups.edit-group', $this->batchId))->assertOk();
    expect($this->actingAs($this->teacherUserA2)->get(route('student-weekly-followups.edit-group', $this->batchId))->status())->not->toBe(200);
});

test('group edit: update by a non-owner teacher is denied and the batch is unchanged', function () {
    $this->actingAs($this->teacherUserA2)->put(route('student-weekly-followups.update-group', $this->batchId), wegPayload($this, ['teacher_id' => $this->teacherA2->id]));
    expect(StudentWeeklyFollowup::withoutGlobalScopes()->where('batch_id', $this->batchId)->first()->teacher_id)->toBe($this->teacherA->id);
});

test('group edit: forging a foreign teacher or foreign circle in update is rejected by validation', function () {
    $this->actingAs($this->supUserA)->put(route('student-weekly-followups.update-group', $this->batchId), wegPayload($this, ['teacher_id' => $this->teacherB->id]))
        ->assertSessionHasErrors('teacher_id');
    $this->actingAs($this->supUserA)->put(route('student-weekly-followups.update-group', $this->batchId), wegPayload($this, [
        'circle_id' => $this->grpB->id, 'teacher_id' => $this->teacherB->id,
        'students' => [['student_id' => $this->studentGrpB->id]],
    ]))->assertSessionHasErrors('circle_id');
    $row = StudentWeeklyFollowup::withoutGlobalScopes()->where('batch_id', $this->batchId)->first();
    expect($row->teacher_id)->toBe($this->teacherA->id)->and($row->circle_id)->toBe($this->grpA->id);
});

test('group edit: a student who stopped after creation blocks re-saving the batch with him', function () {
    $this->studentGrpA->update(['status' => 'متوقف']);
    $this->actingAs($this->teacherUserA)->put(route('student-weekly-followups.update-group', $this->batchId), wegPayload($this));
    expect(session('error'))->not->toBeNull();
});

test('group edit: moving the batch onto a week with an individual followup for the student gives the friendly duplicate message', function () {
    StudentWeeklyFollowup::create([
        'batch_id' => null, 'plan_type' => 'individual',
        'center_id' => $this->centerA->id, 'circle_id' => $this->grpA->id,
        'student_id' => $this->studentGrpA->id, 'teacher_id' => $this->teacherA->id,
        'week_start' => $this->week2Start, 'week_end' => $this->week2End, 'study_days' => [],
    ]);
    $this->actingAs($this->admin)->put(route('student-weekly-followups.update-group', $this->batchId), wegPayload($this, [
        'week_start' => $this->week2Start, 'week_end' => $this->week2End,
    ]));
    expect(session('error'))->toContain('بالفعل');
});

test('group edit: moving the batch to another center circle re-derives center_id from the new circle', function () {
    $this->actingAs($this->admin)->put(route('student-weekly-followups.update-group', $this->batchId), wegPayload($this, [
        'circle_id' => $this->grpB->id, 'teacher_id' => $this->teacherB->id,
        'students' => [['student_id' => $this->studentGrpB->id]],
    ]));
    $rows = StudentWeeklyFollowup::withoutGlobalScopes()->where('batch_id', $this->batchId)->get();
    expect($rows)->not->toBeEmpty();
    foreach ($rows as $row) {
        expect($row->circle_id)->toBe($this->grpB->id)->and($row->center_id)->toBe($this->centerB->id);
    }
});

test('group edit: omitting required keys yields validation errors, never a 500', function () {
    foreach (['circle_id', 'teacher_id', 'week_start', 'students'] as $key) {
        $p = wegPayload($this);
        unset($p[$key]);
        expect($this->actingAs($this->teacherUserA)->put(route('student-weekly-followups.update-group', $this->batchId), $p)->status())->not->toBe(500);
    }
});

test('group edit: nonexistent batch returns 404 on page and update', function () {
    $this->actingAs($this->admin)->get(route('student-weekly-followups.edit-group', 'no-such-batch'))->assertNotFound();
    $this->actingAs($this->admin)->put(route('student-weekly-followups.update-group', 'no-such-batch'), wegPayload($this))->assertNotFound();
});

test('group edit: a non-standard week date is rejected by the strict format', function () {
    $this->actingAs($this->teacherUserA)->put(route('student-weekly-followups.update-group', $this->batchId), wegPayload($this, ['week_start' => '20-11-2026']))
        ->assertSessionHasErrors('week_start');
});
