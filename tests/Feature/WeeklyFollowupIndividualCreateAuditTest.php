<?php

/**
 * فحص إنشاء المتابعة الأسبوعية الفردية (REPORT 227):
 * تسمية معلم خارج النطاق، وتسلل طالب بلا حلقة من فحص النطاق، وطالب
 * حلقة جماعية عبر مسار الفردية، وعدم تطابق حلقة الحمولة مع حلقة الطالب،
 * وتسرب رسائل الاستثناء للعميل.
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

function wiUser(string $role, string $email): User
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

    $this->teacherUserA = wiUser('teacher', 'ta@wi.local');
    $this->teacherA = Teacher::create(['user_id' => $this->teacherUserA->id, 'branch_id' => $this->branchA->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->grpA->id, 'teacher_id' => $this->teacherA->id, 'role' => 'main']);
    DB::table('circle_teacher')->insert(['circle_id' => $this->indA->id, 'teacher_id' => $this->teacherA->id, 'role' => 'main']);
    DB::table('circle_teacher')->insert(['circle_id' => $this->indA2->id, 'teacher_id' => $this->teacherA->id, 'role' => 'main']);

    $this->teacherUserA2 = wiUser('teacher', 'ta2@wi.local');
    $this->teacherA2 = Teacher::create(['user_id' => $this->teacherUserA2->id, 'branch_id' => $this->branchA->id]);

    $this->teacherUserB = wiUser('teacher', 'tb@wi.local');
    $this->teacherB = Teacher::create(['user_id' => $this->teacherUserB->id, 'branch_id' => $this->branchB->id]);

    $this->supUserA = wiUser('supervisor', 'sup@wi.local');
    $this->supTeacherA = Teacher::create(['user_id' => $this->supUserA->id, 'branch_id' => $this->branchA->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branchA->id, 'teacher_id' => $this->supTeacherA->id]);

    $this->admin = wiUser('admin', 'admin@wi.local');

    $mk = fn ($name, $center, $circle) => Student::create([
        'name' => $name, 'center_id' => $center->id, 'circle_id' => $circle?->id,
        'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر',
    ]);
    $this->studentIndA = $mk('طالب فردية أ', $this->centerA, $this->indA);
    $this->studentGrpA = $mk('طالب جماعية أ', $this->centerA, $this->grpA);
    $this->studentNoCircleB = $mk('طالب بلا حلقة ب', $this->centerB, null);

    $this->weekStart = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->format('Y-m-d');
    $this->weekEnd = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->addDays(4)->format('Y-m-d');
});

function wiPayload(object $t, array $override = []): array
{
    return array_merge([
        'student_id' => $t->studentIndA->id, 'teacher_id' => $t->teacherA->id,
        'week_start' => $t->weekStart, 'week_end' => $t->weekEnd,
    ], $override);
}

function wiCount(): int
{
    return StudentWeeklyFollowup::withoutGlobalScope(CenterScope::class)->count();
}

test('individual create: guardian and examiner cannot store, and a role stripped of the permission cannot either', function () {
    foreach ([wiUser('guardian', 'g@wi.local'), wiUser('examiner', 'e@wi.local')] as $outsider) {
        $this->actingAs($outsider)->post(route('student-weekly-followups.store-individual'), wiPayload($this))
            ->assertRedirect();
    }
    Role::findByName('teacher')->revokePermissionTo('create student weekly followups');
    $this->actingAs($this->teacherUserA->fresh())->post(route('student-weekly-followups.store-individual'), wiPayload($this))
        ->assertRedirect();
    expect(wiCount())->toBe(0);
});

test('individual create: naming a teacher outside the actor scope is rejected', function () {
    $this->actingAs($this->supUserA)->post(route('student-weekly-followups.store-individual'), wiPayload($this, [
        'teacher_id' => $this->teacherB->id,
    ]))->assertSessionHasErrors('teacher_id');

    $this->actingAs($this->teacherUserA)->post(route('student-weekly-followups.store-individual'), wiPayload($this, [
        'teacher_id' => $this->teacherA2->id,
    ]))->assertSessionHasErrors('teacher_id');

    expect(wiCount())->toBe(0);
});

test('individual create: a circle-less foreign student can no longer slip past the scope check', function () {
    $this->actingAs($this->teacherUserA)->post(route('student-weekly-followups.store-individual'), wiPayload($this, [
        'student_id' => $this->studentNoCircleB->id,
    ]))->assertRedirect();
    expect(wiCount())->toBe(0);
});

test('individual create: a group-circle student is refused here, and a mismatched payload circle is refused', function () {
    $this->actingAs($this->teacherUserA)->post(route('student-weekly-followups.store-individual'), wiPayload($this, [
        'student_id' => $this->studentGrpA->id,
    ]))->assertSessionHas('error');
    expect(wiCount())->toBe(0);

    $this->actingAs($this->teacherUserA)->post(route('student-weekly-followups.store-individual'), wiPayload($this, [
        'circle_id' => $this->indA2->id,
    ]))->assertSessionHas('error');
    expect(wiCount())->toBe(0);
});

test('individual create: omitting any required key is a validation error, never a 500', function () {
    foreach (['student_id', 'teacher_id', 'week_start', 'week_end'] as $key) {
        $payload = wiPayload($this);
        unset($payload[$key]);
        $this->actingAs($this->admin)->post(route('student-weekly-followups.store-individual'), $payload)
            ->assertSessionHasErrors($key);
    }
    expect(wiCount())->toBe(0);
});

test('individual create: non-standard week dates are rejected by the strict format', function () {
    $this->actingAs($this->admin)->post(route('student-weekly-followups.store-individual'), wiPayload($this, [
        'week_start' => '2026/11/20',
    ]))->assertSessionHasErrors('week_start');
    expect(wiCount())->toBe(0);
});

test('individual create: a database failure shows a generic message with no exception text', function () {
    $this->mock(\App\Services\WeeklyFollowupService::class, function ($m) {
        $m->shouldReceive('createIndividualFollowup')->andThrow(new \Exception('SQLSTATE[23000] probe-secret'));
        $m->shouldIgnoreMissing();
    });
    $this->actingAs($this->admin)->post(route('student-weekly-followups.store-individual'), wiPayload($this));
    expect(session('error'))->not->toBeNull()
        ->and(session('error'))->not->toContain('SQLSTATE')
        ->and(session('error'))->not->toContain('probe-secret');
    expect(wiCount())->toBe(0);
});

test('individual create: happy path still works and derives the center from the student circle', function () {
    $this->actingAs($this->teacherUserA)->post(route('student-weekly-followups.store-individual'), wiPayload($this))
        ->assertRedirect(route('student-weekly-followups.index-individual'));
    $f = StudentWeeklyFollowup::withoutGlobalScope(CenterScope::class)->first();
    expect(wiCount())->toBe(1)
        ->and($f->plan_type)->toBe('individual')
        ->and($f->center_id)->toBe($this->centerA->id)
        ->and($f->circle_id)->toBe($this->indA->id);
});
