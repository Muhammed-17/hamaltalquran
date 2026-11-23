<?php

/*
|--------------------------------------------------------------------------
| سلامة الطلاب والمعلمين والحلقات (فحص 2026-11-21)
|--------------------------------------------------------------------------
| - صفحات الحلقات كانت 404 للمدير والمشرف حتى في مركزهم: فلتر الكنترولر
|   وسياسة الحلقة قارنا بعمود center_id غير الموجود على المعلمين (يُشتق
|   من الفرع) — ونفس المقارنة الفارغة (null === null) في سياسات المعلم
|   والمستخدم كانت ستسمح بتجاوز المركز لأي دور يملك الصلاحية.
| - إنشاء طالب أو نقله لم يتحقق من نطاق المركز/الحلقة/المشرف المستهدفة.
| - مسار assign-circle كان يشير لدالة غير موجودة في الكنترولر (500).
*/

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->centerA = Center::create(['name' => 'مركز أ']);
    $this->centerB = Center::create(['name' => 'مركز ب']);
    $this->branchA = Branch::create(['center_id' => $this->centerA->id, 'name' => 'فرع أ']);
    $this->branchB = Branch::create(['center_id' => $this->centerB->id, 'name' => 'فرع ب']);
    $this->circleA = Circle::create(['name' => 'حلقة أ', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchA->id]);
    $this->circleA2 = Circle::create(['name' => 'حلقة أ2', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchA->id]);
    $this->circleB = Circle::create(['name' => 'حلقة ب', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchB->id]);

    $mk = function (string $role, ?Branch $branch) {
        $u = User::factory()->create(['status' => 'active']);
        $u->assignRole($role);
        $t = Teacher::create(['user_id' => $u->id, 'branch_id' => $branch?->id]);
        return [$u, $t];
    };

    [$this->admin] = $mk('admin', null);
    [$this->managerA, $this->managerTeacherA] = $mk('manager', $this->branchA);
    [$this->supervisorA, $this->supervisorTeacherA] = $mk('supervisor', $this->branchA);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branchA->id, 'teacher_id' => $this->supervisorTeacherA->id, 'created_at' => now(), 'updated_at' => now()]);
    [$this->teacherBUser, $this->teacherB] = $mk('teacher', $this->branchB);

    $this->studentA = Student::create([
        'name' => 'طالب أ', 'center_id' => $this->centerA->id, 'circle_id' => $this->circleA->id,
        'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر', 'supervisor_id' => $this->supervisorTeacherA->id,
    ]);

    $this->payload = fn (array $over = []) => array_merge([
        'supervisor_id' => $this->supervisorTeacherA->id,
        'join_date' => now()->format('Y-m-d'),
        'applicant' => 'الأب',
        'name' => 'طالب جديد',
        'gender' => 'ذكر',
        'address' => 'عنوان',
        'center_id' => $this->centerA->id,
        'guardian_id' => 'none',
        'educational_stage' => 'المرحلة الابتدائية',
        'education_type' => 'حكومي',
        'school_grade' => 'الصف الرابع',
        'previous_school' => 'مدرسة سابقة',
        'health_status' => 'سليم',
        'learning_difficulties' => 'لا يوجد',
        'personal_traits' => 'هادئ',
        'student_exit_status' => 'منتظم',
        'reading' => 'جيدة',
        'center_entry_level' => 'construction',
        'study_system' => 'group',
        'circle_id' => $this->circleA->id,
        'status' => 'مقيد',
        'decision' => 'مقبول',
        'subscription_fees' => 'مدفوع',
        'received_tools' => 'تم الاستلام',
    ], $over);
});

test('manager and supervisor can open circle pages in their own center', function () {
    $this->actingAs($this->managerA)->get(route('circles.show', $this->circleA))->assertOk();
    $this->actingAs($this->supervisorA)->get(route('circles.show', $this->circleA))->assertOk();
    $this->actingAs($this->managerA)->get(route('circles.edit', $this->circleA))->assertOk();

    // والحلقة الأجنبية ما زالت مغلقة
    $this->actingAs($this->managerA)->get(route('circles.show', $this->circleB))->assertNotFound();
    expect($this->managerA->can('view', $this->circleA))->toBeTrue()
        ->and($this->managerA->can('view', $this->circleB))->toBeFalse();
});

test('manager cannot create a student in a foreign center or circle', function () {
    $this->actingAs($this->managerA)->post(route('students.store'), ($this->payload)([
        'name' => 'طالب أجنبي',
        'center_id' => $this->centerB->id,
        'circle_id' => $this->circleB->id,
        'supervisor_id' => $this->teacherB->id,
    ]));
    expect(Student::withoutGlobalScopes()->where('name', 'طالب أجنبي')->exists())->toBeFalse();

    // والإنشاء داخل مركزه يعمل
    $this->actingAs($this->managerA)->post(route('students.store'), ($this->payload)(['name' => 'طالب شرعي']));
    expect(Student::withoutGlobalScopes()->where('name', 'طالب شرعي')->exists())->toBeTrue();
});

test('manager cannot move a student to a foreign circle but can within his center', function () {
    $this->actingAs($this->managerA)->put(route('students.update', $this->studentA), ($this->payload)([
        'name' => 'طالب أ',
        'circle_id' => $this->circleB->id,
        'center_id' => $this->centerB->id,
    ]));
    $after = Student::withoutGlobalScopes()->find($this->studentA->id);
    expect($after->circle_id)->toBe($this->circleA->id)
        ->and($after->center_id)->toBe($this->centerA->id);

    // نقل داخلي مشروع داخل نفس المركز
    $this->actingAs($this->managerA)->put(route('students.update', $this->studentA), ($this->payload)([
        'name' => 'طالب أ',
        'circle_id' => $this->circleA2->id,
    ]));
    expect(Student::withoutGlobalScopes()->find($this->studentA->id)->circle_id)->toBe($this->circleA2->id);
});

test('teacher policy compares branch centers not nulls', function () {
    // منح الصلاحيات يدويًا لعزل فحص النطاق في السياسة نفسها
    $this->managerA->givePermissionTo(['edit teachers', 'delete teachers', 'toggle teacher status']);

    expect($this->managerA->can('update', $this->teacherB))->toBeFalse()
        ->and($this->managerA->can('delete', $this->teacherB))->toBeFalse()
        ->and($this->managerA->can('toggle', $this->teacherB))->toBeFalse()
        ->and($this->managerA->can('update', $this->supervisorTeacherA))->toBeTrue();
});

test('the dead assign-circle route no longer errors', function () {
    $this->actingAs($this->admin)->post(route('students.index') . '/' . $this->studentA->id . '/assign-circle', [
        'circle_id' => $this->circleA2->id,
    ])->assertNotFound();
});
