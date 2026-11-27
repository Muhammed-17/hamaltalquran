<?php

/**
 * فحص تعديل الملاحظة السلوكية (REPORT 231):
 * بوابة الاعتماد ذاتها سليمة (status/action_taken لا يمران من التعديل)،
 * لكن التعديل كان يفتح: تاريخ متابعة قبل أي إجراء، وإعادة كتابة الواقعة
 * بعد تسجيل الإجراء رغم قفل الواجهة لها، وأزواج طالب/حلقة غير متسقة.
 */

use App\Models\BehavioralNote;
use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

function bneUser(string $role, string $email): User
{
    $u = User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('Test123!'), 'status' => 'active']);
    $u->assignRole($role);
    return $u;
}

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->centerA = Center::create(['name' => 'مركز أ']);
    $this->centerB = Center::create(['name' => 'مركز ب']);
    $this->branchA = Branch::create(['name' => 'فرع أ', 'center_id' => $this->centerA->id]);
    $this->branchB = Branch::create(['name' => 'فرع ب', 'center_id' => $this->centerB->id]);
    $this->circleA = Circle::create(['name' => 'حلقة أ', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchA->id]);
    $this->circleA2 = Circle::create(['name' => 'حلقة أ2', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchA->id]);
    $this->circleB = Circle::create(['name' => 'حلقة ب', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchB->id]);

    $this->teacherUserA = bneUser('teacher', 'ta@bne.local');
    $this->teacherA = Teacher::create(['user_id' => $this->teacherUserA->id, 'branch_id' => $this->branchA->id]);
    DB::table('circle_teacher')->insert([
        ['circle_id' => $this->circleA->id, 'teacher_id' => $this->teacherA->id, 'role' => 'main'],
        ['circle_id' => $this->circleA2->id, 'teacher_id' => $this->teacherA->id, 'role' => 'main'],
    ]);

    $this->teacherUserA2 = bneUser('teacher', 'ta2@bne.local');
    $this->teacherA2 = Teacher::create(['user_id' => $this->teacherUserA2->id, 'branch_id' => $this->branchA->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circleA->id, 'teacher_id' => $this->teacherA2->id, 'role' => 'assistant']);

    $this->teacherUserB = bneUser('teacher', 'tb@bne.local');
    $this->teacherB = Teacher::create(['user_id' => $this->teacherUserB->id, 'branch_id' => $this->branchB->id]);

    $this->supUserA = bneUser('supervisor', 'sup@bne.local');
    $this->supTeacherA = Teacher::create(['user_id' => $this->supUserA->id, 'branch_id' => $this->branchA->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branchA->id, 'teacher_id' => $this->supTeacherA->id]);

    $this->admin = bneUser('admin', 'admin@bne.local');

    $mk = fn ($name, $center, $circle) => Student::create([
        'name' => $name, 'center_id' => $center->id, 'circle_id' => $circle?->id,
        'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر',
    ]);
    $this->studentA = $mk('طالب أ', $this->centerA, $this->circleA);
    $this->studentA2 = $mk('طالب أ2', $this->centerA, $this->circleA2);
    $this->studentB = $mk('طالب ب', $this->centerB, $this->circleB);

    $this->note = BehavioralNote::create([
        'student_id' => $this->studentA->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherA->id, 'incident_at' => '2026-11-01 10:00',
        'behavior' => 'سلوك أصلي', 'status' => 'pending',
    ]);
});

function bnePayload(object $t, array $override = []): array
{
    return array_merge([
        'student_id' => $t->studentA->id, 'circle_id' => $t->circleA->id,
        'teacher_id' => $t->teacherA->id, 'incident_at' => '2026-11-01 10:00',
        'behavior' => 'سلوك أصلي',
    ], $override);
}

test('behavioral edit: page opens for the reporter and an in-scope supervisor, not for another teacher or a stripped role', function () {
    $this->actingAs($this->teacherUserA)->get(route('behavioral-notes.edit', $this->note))->assertOk();
    $this->actingAs($this->supUserA)->get(route('behavioral-notes.edit', $this->note))->assertOk();
    expect($this->actingAs($this->teacherUserA2)->get(route('behavioral-notes.edit', $this->note))->status())->not->toBe(200);

    Role::findByName('teacher')->revokePermissionTo('edit behavioral notes');
    expect($this->actingAs($this->teacherUserA->fresh())->get(route('behavioral-notes.edit', $this->note))->status())->not->toBe(200);
});

test('behavioral edit: forging status and action_taken through update is ignored — the approve gate holds', function () {
    $this->actingAs($this->teacherUserA)->put(route('behavioral-notes.update', $this->note), bnePayload($this, [
        'status' => 'action_taken', 'action_taken' => 'إجراء مزيّف',
    ]));
    $fresh = $this->note->fresh();
    expect($fresh->status)->toBe('pending')->and($fresh->action_taken)->toBeNull();
});

test('behavioral edit: a teacher without approve cannot set follow_up_at while the note is pending', function () {
    $this->actingAs($this->teacherUserA)->put(route('behavioral-notes.update', $this->note), bnePayload($this, [
        'follow_up_at' => '2026-12-01',
    ]))->assertSessionHasErrors('follow_up_at');
    expect($this->note->fresh()->follow_up_at)->toBeNull();
});

test('behavioral edit: incident fields are locked after the supervisor records an action, follow-up fields stay editable', function () {
    $this->actingAs($this->supUserA)->put(route('behavioral-notes.update-action', $this->note), [
        'action_taken' => 'تم التنبيه', 'status' => 'action_taken', 'follow_up_at' => '2026-11-20',
    ])->assertRedirect();
    expect($this->note->fresh()->status)->toBe('action_taken');

    // إعادة كتابة الواقعة بعد الإجراء: مرفوضة
    $this->actingAs($this->teacherUserA)->put(route('behavioral-notes.update', $this->note), bnePayload($this, [
        'behavior' => 'وصف مُعاد كتابته', 'incident_at' => '2026-10-01 09:00',
    ]))->assertSessionHasErrors(['behavior', 'incident_at']);
    $fresh = $this->note->fresh();
    expect($fresh->behavior)->toBe('سلوك أصلي')->and($fresh->incident_at->format('Y-m-d'))->toBe('2026-11-01');

    // وقسم المتابعة (الوضع الحالي + تاريخ المتابعة) يبقى قابلًا للتعديل كما تعرضه الواجهة
    $this->actingAs($this->supUserA)->put(route('behavioral-notes.update', $this->note), [
        'current_status' => 'تحسّن ملحوظ', 'follow_up_at' => '2026-12-05',
    ])->assertRedirect(route('behavioral-notes.index'));
    $fresh = $this->note->fresh();
    expect($fresh->current_status)->toBe('تحسّن ملحوظ')
        ->and($fresh->follow_up_at->format('Y-m-d'))->toBe('2026-12-05');
});

test('behavioral edit: a student/circle pair that does not match membership is rejected', function () {
    $this->actingAs($this->supUserA)->put(route('behavioral-notes.update', $this->note), bnePayload($this, [
        'student_id' => $this->studentA2->id, // طالب حلقة أ2 مع حلقة أ
    ]))->assertSessionHasErrors('student_id');
    $fresh = $this->note->fresh();
    expect($fresh->student_id)->toBe($this->studentA->id)->and($fresh->circle_id)->toBe($this->circleA->id);
});

test('behavioral edit: sending only a student from another accessible circle cannot leave the note inconsistent', function () {
    $this->actingAs($this->supUserA)->put(route('behavioral-notes.update', $this->note), [
        'student_id' => $this->studentA2->id,
    ])->assertSessionHasErrors('student_id');
    expect($this->note->fresh()->student_id)->toBe($this->studentA->id);
});

test('behavioral edit: foreign student and foreign teacher are rejected by scope checks', function () {
    $this->actingAs($this->supUserA)->put(route('behavioral-notes.update', $this->note), bnePayload($this, [
        'student_id' => $this->studentB->id, 'circle_id' => $this->circleB->id,
    ]))->assertSessionHasErrors(['student_id', 'circle_id']);
    $this->actingAs($this->supUserA)->put(route('behavioral-notes.update', $this->note), bnePayload($this, [
        'teacher_id' => $this->teacherB->id,
    ]))->assertSessionHasErrors('teacher_id');
    $fresh = $this->note->fresh();
    expect($fresh->student_id)->toBe($this->studentA->id)->and($fresh->teacher_id)->toBe($this->teacherA->id);
});

test('behavioral edit: an empty payload is a harmless no-op, and a nonexistent note is 404', function () {
    expect($this->actingAs($this->teacherUserA)->put(route('behavioral-notes.update', $this->note), [])->status())->not->toBe(500);
    expect($this->note->fresh()->behavior)->toBe('سلوك أصلي');
    $this->actingAs($this->admin)->get('/behavioral-notes/999999/edit')->assertNotFound();
});

test('behavioral edit: the action route stays approve-only — teacher is denied, supervisor records', function () {
    $r = $this->actingAs($this->teacherUserA)->put(route('behavioral-notes.update-action', $this->note), [
        'action_taken' => 'محاولة', 'status' => 'action_taken',
    ]);
    expect($r->status())->not->toBe(200);
    expect($this->note->fresh()->status)->toBe('pending');

    $this->actingAs($this->supUserA)->put(route('behavioral-notes.update-action', $this->note), [
        'action_taken' => 'إجراء رسمي', 'status' => 'under_review',
    ])->assertRedirect();
    expect($this->note->fresh()->status)->toBe('under_review');
});

test('behavioral edit: a non-standard incident date is stored normalized', function () {
    $this->actingAs($this->teacherUserA)->put(route('behavioral-notes.update', $this->note), bnePayload($this, ['incident_at' => '20-11-2026 10:00']));
    expect($this->note->fresh()->incident_at->format('Y-m-d'))->toBe('2026-11-20');
});
