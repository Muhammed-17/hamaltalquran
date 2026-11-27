<?php

/**
 * فحص إنشاء المتابعة الأسبوعية الجماعية (REPORT 226):
 * طلب الإنشاء كان أضعف من طلب التعديل — exists وحده للحلقة والمعلم،
 * بلا قيد نوع الحلقة ولا حالة الطالب، ورسائل الاستثناء تتسرب للعميل.
 */

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\StudentWeeklyFollowup;
use App\Models\Scopes\CenterScope;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RecommendationTemplateSeeder;
use Database\Seeders\SurahSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

function wgUser(string $role, string $email): User
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
    $this->grpB = Circle::create(['name' => 'جماعية ب', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchB->id]);

    $this->teacherUserA = wgUser('teacher', 'ta@wg.local');
    $this->teacherA = Teacher::create(['user_id' => $this->teacherUserA->id, 'branch_id' => $this->branchA->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->grpA->id, 'teacher_id' => $this->teacherA->id, 'role' => 'main']);

    $this->teacherUserA2 = wgUser('teacher', 'ta2@wg.local');
    $this->teacherA2 = Teacher::create(['user_id' => $this->teacherUserA2->id, 'branch_id' => $this->branchA->id]);

    $this->teacherUserB = wgUser('teacher', 'tb@wg.local');
    $this->teacherB = Teacher::create(['user_id' => $this->teacherUserB->id, 'branch_id' => $this->branchB->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->grpB->id, 'teacher_id' => $this->teacherB->id, 'role' => 'main']);

    $this->supUserA = wgUser('supervisor', 'sup@wg.local');
    $this->supTeacherA = Teacher::create(['user_id' => $this->supUserA->id, 'branch_id' => $this->branchA->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branchA->id, 'teacher_id' => $this->supTeacherA->id]);

    $this->admin = wgUser('admin', 'admin@wg.local');

    $mk = fn ($name, $center, $circle, $status = 'مقيد') => Student::create([
        'name' => $name, 'center_id' => $center->id, 'circle_id' => $circle?->id,
        'status' => $status, 'decision' => 'مقبول', 'gender' => 'ذكر',
    ]);
    $this->studentGrpA = $mk('طالب جماعية أ', $this->centerA, $this->grpA);
    $this->studentIndA = $mk('طالب فردية أ', $this->centerA, $this->indA);
    $this->studentGrpB = $mk('طالب جماعية ب', $this->centerB, $this->grpB);
    $this->studentStopped = $mk('طالب متوقف', $this->centerA, $this->grpA, 'متوقف');
    $this->studentNoCircle = $mk('طالب بلا حلقة', $this->centerA, null);

    $this->weekStart = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->format('Y-m-d');
    $this->weekEnd = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->addDays(4)->format('Y-m-d');
});

function wgPayload(object $t, array $override = []): array
{
    return array_merge([
        'circle_id' => $t->grpA->id, 'teacher_id' => $t->teacherA->id,
        'week_start' => $t->weekStart, 'week_end' => $t->weekEnd,
        'students' => [['student_id' => $t->studentGrpA->id, 'discipline_level' => 'جيد']],
    ], $override);
}

function wgCount(): int
{
    return StudentWeeklyFollowup::withoutGlobalScope(CenterScope::class)->count();
}

test('group create: guardian and examiner cannot store, and a role stripped of the permission cannot either', function () {
    foreach ([wgUser('guardian', 'g@wg.local'), wgUser('examiner', 'e@wg.local')] as $outsider) {
        $this->actingAs($outsider)->post(route('student-weekly-followups.store-group'), wgPayload($this))
            ->assertRedirect();
    }
    expect(wgCount())->toBe(0);

    Role::findByName('teacher')->revokePermissionTo('create student weekly followups');
    $this->actingAs($this->teacherUserA->fresh())->post(route('student-weekly-followups.store-group'), wgPayload($this))
        ->assertRedirect();
    expect(wgCount())->toBe(0);
});

test('group create: naming a teacher outside the actor scope is rejected', function () {
    // مشرف يسمّي معلم مركز آخر
    $this->actingAs($this->supUserA)->post(route('student-weekly-followups.store-group'), wgPayload($this, [
        'teacher_id' => $this->teacherB->id,
    ]))->assertSessionHasErrors('teacher_id');

    // معلم عادي يسمّي معلمًا آخر في فرعه نفسه — الفورم وطلب التعديل يقصرانه على نفسه
    $this->actingAs($this->teacherUserA)->post(route('student-weekly-followups.store-group'), wgPayload($this, [
        'teacher_id' => $this->teacherA2->id,
    ]))->assertSessionHasErrors('teacher_id');

    expect(wgCount())->toBe(0);
});

test('group create: an individual-type circle cannot host a group batch', function () {
    $this->actingAs($this->admin)->post(route('student-weekly-followups.store-group'), wgPayload($this, [
        'circle_id' => $this->indA->id,
        'students' => [['student_id' => $this->studentIndA->id]],
    ]))->assertSessionHasErrors('circle_id');
    expect(wgCount())->toBe(0);
});

test('group create: stopped or circle-less students are rejected with an explicit error and zero records', function () {
    $this->actingAs($this->admin)->post(route('student-weekly-followups.store-group'), wgPayload($this, [
        'students' => [['student_id' => $this->studentGrpA->id], ['student_id' => $this->studentStopped->id]],
    ]))->assertSessionHas('error');
    expect(wgCount())->toBe(0);

    $this->actingAs($this->admin)->post(route('student-weekly-followups.store-group'), wgPayload($this, [
        'students' => [['student_id' => $this->studentNoCircle->id]],
    ]))->assertSessionHas('error');
    expect(wgCount())->toBe(0);
});

test('group create: a payload of only foreign students fails explicitly instead of a fake success', function () {
    $this->actingAs($this->admin)->post(route('student-weekly-followups.store-group'), wgPayload($this, [
        'students' => [['student_id' => $this->studentGrpB->id]],
    ]))->assertSessionHas('error');
    expect(wgCount())->toBe(0);
});

test('group create: omitting any required key is a validation error, never a 500', function () {
    foreach (['week_start', 'week_end', 'circle_id', 'teacher_id', 'students'] as $key) {
        $payload = wgPayload($this);
        unset($payload[$key]);
        $this->actingAs($this->admin)->post(route('student-weekly-followups.store-group'), $payload)
            ->assertSessionHasErrors($key);
    }
    $payload = wgPayload($this, ['students' => [['discipline_level' => 'جيد']]]);
    $this->actingAs($this->admin)->post(route('student-weekly-followups.store-group'), $payload)
        ->assertSessionHasErrors('students.0.student_id');
    expect(wgCount())->toBe(0);
});

test('group create: non-standard week dates are rejected by the strict format', function () {
    $this->actingAs($this->admin)->post(route('student-weekly-followups.store-group'), wgPayload($this, [
        'week_start' => '20-11-2026',
    ]))->assertSessionHasErrors('week_start');
    expect(wgCount())->toBe(0);
});

test('group create: resubmission hits the duplicate guard, and a future week is accepted as a plan', function () {
    $this->actingAs($this->teacherUserA)->post(route('student-weekly-followups.store-group'), wgPayload($this))
        ->assertSessionMissing('error');
    expect(wgCount())->toBe(1);

    $this->actingAs($this->teacherUserA)->post(route('student-weekly-followups.store-group'), wgPayload($this))
        ->assertSessionHas('error');
    expect(wgCount())->toBe(1);

    $future = now()->addWeeks(2)->startOfWeek(\Carbon\Carbon::SATURDAY);
    $this->actingAs($this->teacherUserA)->post(route('student-weekly-followups.store-group'), wgPayload($this, [
        'week_start' => $future->format('Y-m-d'), 'week_end' => $future->copy()->addDays(4)->format('Y-m-d'),
    ]))->assertSessionMissing('error');
    expect(wgCount())->toBe(2);
});

test('group create: a database failure shows a generic message with no exception text', function () {
    $this->mock(\App\Services\WeeklyFollowupService::class, function ($m) {
        $m->shouldReceive('createBatch')->andThrow(new \Exception('SQLSTATE[23000] probe-secret'));
        $m->shouldReceive('validateStudentsInCircle')->andReturn([]);
        $m->shouldReceive('getDuplicateStudents')->andReturn(collect());
    });
    $this->actingAs($this->admin)->post(route('student-weekly-followups.store-group'), wgPayload($this));
    expect(session('error'))->not->toBeNull()
        ->and(session('error'))->not->toContain('SQLSTATE')
        ->and(session('error'))->not->toContain('probe-secret');
    expect(wgCount())->toBe(0);
});
