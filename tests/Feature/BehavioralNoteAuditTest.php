<?php

/**
 * تدقيق الملاحظات السلوكية (REPORT 151):
 * ثغرات النطاق في الإنشاء/التعديل (exists وحده لا يكفي)، تسريب قائمة
 * الطلاب في النموذج، وتوسيع عمود current_status إلى TEXT.
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

function bnUser(string $role, string $email): User
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

    $this->teacherUserA = bnUser('teacher', 'ta@bn.local');
    $this->teacherA = Teacher::create(['user_id' => $this->teacherUserA->id, 'branch_id' => $this->branchA->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circleA->id, 'teacher_id' => $this->teacherA->id, 'role' => 'main']);

    $this->teacherUserA2 = bnUser('teacher', 'ta2@bn.local');
    $this->teacherA2 = Teacher::create(['user_id' => $this->teacherUserA2->id, 'branch_id' => $this->branchA->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circleA->id, 'teacher_id' => $this->teacherA2->id, 'role' => 'assistant']);

    $this->teacherUserB = bnUser('teacher', 'tb@bn.local');
    $this->teacherB = Teacher::create(['user_id' => $this->teacherUserB->id, 'branch_id' => $this->branchB->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circleB->id, 'teacher_id' => $this->teacherB->id, 'role' => 'main']);

    $this->supUserA = bnUser('supervisor', 'sup@bn.local');
    $this->supTeacherA = Teacher::create(['user_id' => $this->supUserA->id, 'branch_id' => $this->branchA->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branchA->id, 'teacher_id' => $this->supTeacherA->id]);

    $this->managerUserA = bnUser('manager', 'mgr@bn.local');
    Teacher::create(['user_id' => $this->managerUserA->id, 'branch_id' => $this->branchA->id]);

    $this->adminBn = bnUser('admin', 'admin@bn.local');

    $this->studentA = Student::create(['name' => 'طالب أ', 'center_id' => $this->centerA->id, 'circle_id' => $this->circleA->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
    $this->studentB = Student::create(['name' => 'طالب ب', 'center_id' => $this->centerB->id, 'circle_id' => $this->circleB->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
});

function bnPayload(object $t, array $override = []): array
{
    return array_merge([
        'student_id' => $t->studentA->id, 'circle_id' => $t->circleA->id,
        'teacher_id' => $t->teacherA->id, 'incident_at' => '2026-11-01 10:00',
        'behavior' => 'سلوك موثّق',
    ], $override);
}

test('store: cross-center trio is rejected for teacher and supervisor alike', function () {
    $cross = bnPayload($this, [
        'student_id' => $this->studentB->id, 'circle_id' => $this->circleB->id, 'teacher_id' => $this->teacherB->id,
    ]);
    $this->actingAs($this->teacherUserA)->post(route('behavioral-notes.store'), $cross)
        ->assertSessionHasErrors('circle_id');
    $this->actingAs($this->supUserA)->post(route('behavioral-notes.store'), $cross)
        ->assertSessionHasErrors('circle_id');
    expect(BehavioralNote::withoutGlobalScopes()->count())->toBe(0);
});

test('store: student outside the chosen circle is rejected even inside the same center', function () {
    $this->actingAs($this->teacherUserA)->post(route('behavioral-notes.store'), bnPayload($this, [
        'student_id' => $this->studentB->id, // طالب المركز ب مع حلقة المركز أ
    ]))->assertSessionHasErrors('student_id');
    expect(BehavioralNote::withoutGlobalScopes()->count())->toBe(0);
});

test('store: happy paths — teacher in his circle, supervisor in his branch, long current_status text', function () {
    $long = str_repeat('متابعة ', 20); // > 50 حرفًا — كان يفشل على MySQL قبل توسيع العمود
    $this->actingAs($this->teacherUserA)->post(route('behavioral-notes.store'), bnPayload($this, ['current_status' => $long]))
        ->assertSessionHasNoErrors();
    $this->actingAs($this->supUserA)->post(route('behavioral-notes.store'), bnPayload($this))
        ->assertSessionHasNoErrors();
    expect(BehavioralNote::withoutGlobalScopes()->count())->toBe(2)
        ->and(BehavioralNote::withoutGlobalScopes()->first()->current_status)->toBe(trim($long));
});

test('update: reassigning a note to another center via edit is rejected, plain edit passes', function () {
    $note = BehavioralNote::create(bnPayload($this) + ['incident_at' => '2026-11-01 10:00']);

    $this->actingAs($this->teacherUserA)->put(route('behavioral-notes.update', $note), [
        'student_id' => $this->studentB->id, 'circle_id' => $this->circleB->id, 'teacher_id' => $this->teacherB->id,
    ])->assertSessionHasErrors();
    $fresh = BehavioralNote::withoutGlobalScopes()->find($note->id);
    expect($fresh->student_id)->toBe($this->studentA->id)->and($fresh->circle_id)->toBe($this->circleA->id);

    $this->actingAs($this->teacherUserA)->put(route('behavioral-notes.update', $note), ['behavior' => 'وصف معدّل'])
        ->assertSessionHasNoErrors();
    expect(BehavioralNote::withoutGlobalScopes()->find($note->id)->behavior)->toBe('وصف معدّل');
});

test('create form: student list is scoped to the viewer circles', function () {
    $students = collect($this->actingAs($this->teacherUserA)->get(route('behavioral-notes.create'))->viewData('students'));
    expect($students->contains('id', $this->studentA->id))->toBeTrue()
        ->and($students->contains('id', $this->studentB->id))->toBeFalse();

    $all = collect($this->actingAs($this->adminBn)->get(route('behavioral-notes.create'))->viewData('students'));
    expect($all->contains('id', $this->studentB->id))->toBeTrue();
});

test('access matrix: colleague edit denied, teacher cannot record action, supervisor can, outsiders blocked', function () {
    $note = BehavioralNote::create(bnPayload($this, ['teacher_id' => $this->teacherA2->id]));

    // زميل في نفس الحلقة لا يعدّل ملاحظة غيره (السياسة: ملاحظاته فقط)
    expect($this->actingAs($this->teacherUserA)->put(route('behavioral-notes.update', $note), ['behavior' => 'عبث'])->status())
        ->toBeIn([302, 403]);
    expect(BehavioralNote::withoutGlobalScopes()->find($note->id)->behavior)->toBe('سلوك موثّق');

    // المعلم لا يسجّل إجراء (لا صلاحية approve)، المشرف يسجّل
    $this->actingAs($this->teacherUserA2)->put(route('behavioral-notes.update-action', $note), [
        'action_taken' => 'إجراء ذاتي', 'status' => 'action_taken',
    ]);
    expect(BehavioralNote::withoutGlobalScopes()->find($note->id)->action_taken)->toBeNull();

    $this->actingAs($this->supUserA)->put(route('behavioral-notes.update-action', $note), [
        'action_taken' => 'تنبيه ولي الأمر', 'status' => 'action_taken',
    ])->assertSessionHasNoErrors();
    $fresh = BehavioralNote::withoutGlobalScopes()->find($note->id);
    expect($fresh->action_taken)->toBe('تنبيه ولي الأمر')->and($fresh->status)->toBe('action_taken');

    // مدير المركز أ لا يفتح ملاحظة المركز ب، وولي الأمر محجوب عن القائمة
    $noteB = BehavioralNote::create(bnPayload($this, [
        'student_id' => $this->studentB->id, 'circle_id' => $this->circleB->id, 'teacher_id' => $this->teacherB->id,
    ]));
    $this->actingAs($this->managerUserA)->get(route('behavioral-notes.show', $noteB))->assertNotFound();
    $guardian = bnUser('guardian', 'g@bn.local');
    expect($this->actingAs($guardian)->get(route('behavioral-notes.index'))->status())->toBeIn([302, 403]);
});
