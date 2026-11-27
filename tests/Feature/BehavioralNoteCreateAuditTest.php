<?php

/**
 * فحص إنشاء الملاحظات السلوكية وقائمتها (REPORT 228) — استكمال لفحص 151:
 * البوابات، وطالب بلا حلقة، وتزوير حقول الإجراء/الحالة، وتطبيع التاريخ،
 * ونطاق القائمة. (طالب متوقف وتاريخ مستقبلي: قاعدتان حاليتان موثقتان
 * في ملف الفحص بانتظار قرار صاحب الموقع — لا اختبار يثبّتهما هنا.)
 */

use App\Models\BehavioralNote;
use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

function bncUser(string $role, string $email): User
{
    $u = User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('Test123!'), 'status' => 'active']);
    $u->assignRole($role);
    return $u;
}

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->centerA = Center::create(['name' => 'مركز أ']);
    $this->centerB = Center::create(['name' => 'مركز ب']);
    $this->branchA = Branch::create(['name' => 'فرع أ', 'center_id' => $this->centerA->id]);
    $this->branchB = Branch::create(['name' => 'فرع ب', 'center_id' => $this->centerB->id]);
    $this->circleA = Circle::create(['name' => 'حلقة أ', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchA->id]);
    $this->circleB = Circle::create(['name' => 'حلقة ب', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchB->id]);

    $this->teacherUserA = bncUser('teacher', 'ta@bnc.local');
    $this->teacherA = Teacher::create(['user_id' => $this->teacherUserA->id, 'branch_id' => $this->branchA->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circleA->id, 'teacher_id' => $this->teacherA->id, 'role' => 'main']);

    $this->teacherUserB = bncUser('teacher', 'tb@bnc.local');
    $this->teacherB = Teacher::create(['user_id' => $this->teacherUserB->id, 'branch_id' => $this->branchB->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circleB->id, 'teacher_id' => $this->teacherB->id, 'role' => 'main']);

    $this->admin = bncUser('admin', 'admin@bnc.local');

    $this->studentA = Student::create(['name' => 'طالب أ', 'center_id' => $this->centerA->id, 'circle_id' => $this->circleA->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
    $this->studentB = Student::create(['name' => 'طالب ب', 'center_id' => $this->centerB->id, 'circle_id' => $this->circleB->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
    $this->studentNoCircle = Student::create(['name' => 'طالب بلا حلقة', 'center_id' => $this->centerA->id, 'circle_id' => null, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
});

function bncPayload(object $t, array $override = []): array
{
    return array_merge([
        'student_id' => $t->studentA->id, 'circle_id' => $t->circleA->id,
        'teacher_id' => $t->teacherA->id, 'incident_at' => '2026-11-01 10:00',
        'behavior' => 'سلوك موثّق',
    ], $override);
}

test('behavioral create: guardian and examiner are denied the module, and a stripped role cannot store', function () {
    foreach ([bncUser('guardian', 'g@bnc.local'), bncUser('examiner', 'e@bnc.local')] as $outsider) {
        $this->actingAs($outsider)->get(route('behavioral-notes.index'))->assertRedirect();
        $this->actingAs($outsider)->post(route('behavioral-notes.store'), bncPayload($this))->assertRedirect();
    }
    expect(BehavioralNote::withoutGlobalScopes()->count())->toBe(0);

    Role::findByName('teacher')->revokePermissionTo('create behavioral notes');
    $this->actingAs($this->teacherUserA->fresh())->post(route('behavioral-notes.store'), bncPayload($this))
        ->assertRedirect();
    expect(BehavioralNote::withoutGlobalScopes()->count())->toBe(0);
});

test('behavioral create: a circle-less student is rejected by the membership check', function () {
    $this->actingAs($this->teacherUserA)->post(route('behavioral-notes.store'), bncPayload($this, [
        'student_id' => $this->studentNoCircle->id,
    ]))->assertSessionHasErrors('student_id');
    expect(BehavioralNote::withoutGlobalScopes()->count())->toBe(0);
});

test('behavioral create: forged action and status fields are ignored on store', function () {
    $this->actingAs($this->teacherUserA)->post(route('behavioral-notes.store'), bncPayload($this, [
        'status' => 'action_taken', 'action_taken' => 'إجراء مزور', 'follow_up_at' => '2026-12-01',
    ]))->assertSessionHasNoErrors();
    $note = BehavioralNote::withoutGlobalScopes()->first();
    expect($note->status)->toBe(BehavioralNote::STATUS_PENDING)
        ->and($note->action_taken)->toBeNull()
        ->and($note->follow_up_at)->toBeNull();
});

test('behavioral create: omitting any required key is a validation error, never a 500', function () {
    foreach (['student_id', 'circle_id', 'teacher_id', 'incident_at', 'behavior'] as $key) {
        $payload = bncPayload($this);
        unset($payload[$key]);
        $this->actingAs($this->teacherUserA)->post(route('behavioral-notes.store'), $payload)
            ->assertSessionHasErrors($key);
    }
    expect(BehavioralNote::withoutGlobalScopes()->count())->toBe(0);
});

test('behavioral create: a non-standard incident date is stored normalized', function () {
    $this->actingAs($this->teacherUserA)->post(route('behavioral-notes.store'), bncPayload($this, [
        'incident_at' => '20-11-2026',
    ]))->assertSessionHasNoErrors();
    expect(BehavioralNote::withoutGlobalScopes()->first()->incident_at->format('Y-m-d'))->toBe('2026-11-20');
});

test('behavioral index: a teacher sees only his circle notes even when filtering by a foreign student', function () {
    BehavioralNote::create(bncPayload($this));
    BehavioralNote::create([
        'student_id' => $this->studentB->id, 'circle_id' => $this->circleB->id,
        'teacher_id' => $this->teacherB->id, 'incident_at' => '2026-11-02 10:00', 'behavior' => 'واقعة ب',
    ]);

    $notes = collect($this->actingAs($this->teacherUserA)->get(route('behavioral-notes.index'))->viewData('behavioralNotes')->items());
    expect($notes)->toHaveCount(1)
        ->and($notes->first()->student_id)->toBe($this->studentA->id);

    $filtered = collect($this->actingAs($this->teacherUserA)->get(route('behavioral-notes.index', ['student_id' => $this->studentB->id]))->viewData('behavioralNotes')->items());
    expect($filtered)->toHaveCount(0);
});
