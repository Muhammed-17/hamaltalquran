<?php

use App\Models\{Branch, Center, Circle, Student, Teacher, User};
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\{DB, Hash};

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $centerA = Center::create(['name' => 'مركز أ']);
    $centerB = Center::create(['name' => 'مركز ب']);
    $branchA = Branch::create(['name' => 'فرع أ', 'center_id' => $centerA->id]);
    $branchB = Branch::create(['name' => 'فرع ب', 'center_id' => $centerB->id]);
    $this->circleA = Circle::create(['name' => 'حلقة أ', 'type' => 'group', 'level' => 'build', 'branch_id' => $branchA->id]);
    $circleB = Circle::create(['name' => 'حلقة ب', 'type' => 'group', 'level' => 'build', 'branch_id' => $branchB->id]);

    $mk = function (string $email, string $role) {
        $u = User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('Test123!'), 'status' => 'active']);
        $u->assignRole($role);
        return $u;
    };

    $this->guardian = $mk('guardian@show.local', 'guardian');
    $this->examiner = $mk('examiner@show.local', 'examiner');
    $this->teacherUser = $mk('teacher@show.local', 'teacher');
    $teacher = Teacher::create(['user_id' => $this->teacherUser->id, 'branch_id' => $branchA->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circleA->id, 'teacher_id' => $teacher->id, 'role' => 'main', 'created_at' => now(), 'updated_at' => now()]);

    $base = ['decision' => 'مقبول', 'gender' => 'ذكر', 'guardian_id' => $this->guardian->id];
    $this->ownChild = Student::create($base + ['name' => 'ابن الولي', 'center_id' => $centerA->id, 'circle_id' => $this->circleA->id, 'status' => 'مقيد']);
    $this->suspendedChild = Student::create($base + ['name' => 'ابن متوقف', 'center_id' => $centerA->id, 'circle_id' => $this->circleA->id, 'status' => 'متوقف']);
    $this->otherChild = Student::create(['name' => 'طالب آخر', 'center_id' => $centerB->id, 'circle_id' => $circleB->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
});

test('guardian opens his own active child profile with the badges and competitions tabs', function () {
    $r = $this->actingAs($this->guardian)->get(route('students.show', $this->ownChild));
    $r->assertOk()->assertSee('الأوسمة')->assertSee('المسابقات');

    $chunk = $this->actingAs($this->guardian)->getJson(route('students.tab-data', $this->ownChild) . '?tab=attendance&offset=0');
    $chunk->assertOk()->assertJson(['total' => 0, 'has_more' => false]);
});

test('guardian is denied other children and his suspended child, on show and tab-data', function () {
    foreach ([$this->otherChild, $this->suspendedChild] as $student) {
        $r = $this->actingAs($this->guardian)->get(route('students.show', $student));
        expect($r->status())->toBeIn([302, 403]);

        $chunk = $this->actingAs($this->guardian)->getJson(route('students.tab-data', $student) . '?tab=attendance&offset=0');
        expect($chunk->status())->toBeIn([302, 403]);
    }

    // وقائمة الطلاب العامة ما زالت مغلقة على الولي
    $list = $this->actingAs($this->guardian)->get(route('students.index'));
    expect($list->status())->toBeIn([302, 403]);
});

test('circle main teacher opens his student profile; examiner stays out', function () {
    $this->actingAs($this->teacherUser)->get(route('students.show', $this->ownChild))->assertOk();

    $r = $this->actingAs($this->teacherUser)->get(route('students.show', $this->otherChild));
    expect($r->status())->toBeIn([302, 403]);

    $r = $this->actingAs($this->examiner)->get(route('students.show', $this->ownChild));
    expect($r->status())->toBeIn([302, 403]);
});

/**
 * حذف صلاحية view own children (تقرير 208): صار وصول ولي الأمر لصفحة ابنه
 * مربوطًا بدوره مباشرة (مسارًا وسياسة) كأخواتها المحذوفة — الملكية والحالة
 * هما الحاسمان كما كانا.
 */
test('guardian reaches his child by role after view own children deletion', function () {
    expect(\Spatie\Permission\Models\Permission::where('name', 'view own children')->exists())->toBeFalse();

    $this->actingAs($this->guardian)->get(route('students.show', $this->ownChild))->assertOk();
    $this->actingAs($this->guardian)->get(route('students.show', $this->otherChild))->assertStatus(302);
});
