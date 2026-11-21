<?php

/*
|--------------------------------------------------------------------------
| سلامة الترقيم وحجم البيانات المحمّلة (فحص 2026-11-21)
|--------------------------------------------------------------------------
| صفحات القوائم كلها مرقّمة أصلًا (طلاب 30، اشتراكات 20، معلمون 20...).
| المشكلتان هنا كانتا في نماذج الاشتراكات:
| - إنشاء/تعديل الاشتراك كان يضمّن كل طلاب النطاق (مع كل اشتراكاتهم)
|   داخل الصفحة كـ JSON — مع ألف طالب تصبح الصفحة بآلاف السجلات.
|   صار التحميل عند الطلب لكل حلقة عبر subscriptions.students-for-circle.
| - نموذج الطالب كان يحمّل كل أولياء الأمور (User::role('guardian')->get())
|   في كل فتح لصفحة إنشاء/تعديل رغم أن الواجهة تبحث عنهم بـ AJAX أصلًا
|   والمتغير غير مستخدم — حُذف التحميل الميت.
*/

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Subscription;
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
    $this->circleB = Circle::create(['name' => 'حلقة ب', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchB->id]);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');

    $managerUser = User::factory()->create(['status' => 'active']);
    $managerUser->assignRole('manager');
    $this->managerA = $managerUser;
    Teacher::create(['user_id' => $managerUser->id, 'branch_id' => $this->branchA->id]);

    $supUser = User::factory()->create(['status' => 'active']);
    $supUser->assignRole('supervisor');
    $supTeacher = Teacher::create(['user_id' => $supUser->id, 'branch_id' => $this->branchA->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branchA->id, 'teacher_id' => $supTeacher->id, 'created_at' => now(), 'updated_at' => now()]);

    $this->studentA = Student::create(['name' => 'طالب-الحلقة-الأولى', 'center_id' => $this->centerA->id, 'circle_id' => $this->circleA->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر', 'supervisor_id' => $supTeacher->id]);
    $this->studentB = Student::create(['name' => 'طالب-بعيد-جدا', 'center_id' => $this->centerB->id, 'circle_id' => $this->circleB->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر', 'supervisor_id' => $supTeacher->id]);

    $this->subA = Subscription::create(['student_id' => $this->studentA->id, 'circle_id' => $this->circleA->id, 'teacher_id' => $this->admin->id, 'month' => '2026-10-01', 'status' => 'مدفوع', 'amount' => 100, 'payment_method' => 'نقدي', 'paid_at' => now(), 'collected_by' => $this->admin->id]);
});

test('students for circle endpoint returns only that circle and enforces scope', function () {
    // مدير المركز أ يطلب حلقته: طالبها فقط مع اشتراكاته
    $res = $this->actingAs($this->managerA)->getJson(route('subscriptions.students-for-circle', ['circle_id' => $this->circleA->id]));
    $res->assertOk();
    $names = collect($res->json('students'))->pluck('name');
    expect($names)->toContain('طالب-الحلقة-الأولى')->not->toContain('طالب-بعيد-جدا');
    $first = collect($res->json('students'))->firstWhere('id', $this->studentA->id);
    expect($first['subscriptions'])->toHaveCount(1)
        ->and($first['circle']['level'])->toBe('build');

    // حلقة أجنبية: ممنوع
    $this->actingAs($this->managerA)->getJson(route('subscriptions.students-for-circle', ['circle_id' => $this->circleB->id]))
        ->assertForbidden();

    // include_student_id يضمّن طالبًا انتقل لحلقة أخرى (شاشة التعديل)
    $this->studentA->update(['circle_id' => $this->circleB->id]);
    $res2 = $this->actingAs($this->admin)->getJson(route('subscriptions.students-for-circle', [
        'circle_id' => $this->circleA->id, 'include_student_id' => $this->studentA->id,
    ]));
    expect(collect($res2->json('students'))->pluck('id'))->toContain($this->studentA->id);
});

test('subscription create and edit pages no longer embed all students', function () {
    $create = $this->actingAs($this->admin)->get(route('subscriptions.create'));
    $create->assertOk();
    expect($create->getContent())->not->toContain('طالب-بعيد-جدا')
        ->and($create->getContent())->toContain('students-for-circle');

    $edit = $this->actingAs($this->admin)->get(route('subscriptions.edit', $this->subA));
    $edit->assertOk();
    expect($edit->getContent())->not->toContain('طالب-بعيد-جدا')
        ->and($edit->getContent())->toContain('students-for-circle');
});

test('student create and edit pages render without the dead guardians load', function () {
    $this->actingAs($this->admin)->get(route('students.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('students.edit', $this->studentA))->assertOk();
});

test('main listing pages are paginated', function () {
    foreach (range(1, 35) as $i) {
        Student::create(['name' => "طالب ترقيم {$i}", 'center_id' => $this->centerA->id, 'circle_id' => $this->circleA->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر', 'supervisor_id' => $this->studentA->supervisor_id]);
    }

    $res = $this->actingAs($this->admin)->get(route('students.index'));
    $res->assertOk();
    $paginator = $res->viewData('students');
    expect($paginator->perPage())->toBe(30)
        ->and($paginator->total())->toBeGreaterThan(30)
        ->and($paginator->count())->toBe(30);
});
