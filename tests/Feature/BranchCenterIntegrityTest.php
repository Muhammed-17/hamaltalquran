<?php

/*
|--------------------------------------------------------------------------
| سلامة الفروع والمراكز (فحص 2026-11-21)
|--------------------------------------------------------------------------
| - حذف الفرع لم يكن له أي حارس رغم أن الحذف يتسلسل (cascade) إلى
|   الحلقات واشتراكاتها ويصفّر فرع المعلمين المرتبطين.
| - حذف المركز كان يفحص الطلاب المباشرين فقط، فمركز كامل الهيكل
|   (فروع وحلقات ودروس) بلا طلاب مباشرين كان يُمسح بنقرة واحدة.
| - تعيين مشرفي الفرع كان يكتفي بـ exists — أي معلم (حتى بلا دور
|   إشرافي) يصبح مشرف فرع ويكتسب حقوق الإشراف على حلقاته فعليًا.
*/

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');

    $this->center = Center::create(['name' => 'مركز الحذف']);
    $this->branch = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع الحذف', 'established_at' => '2020-01-01']);
    $this->circle = Circle::create(['name' => 'حلقة الحذف', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branch->id]);

    $teacherUser = User::factory()->create(['status' => 'active']);
    $teacherUser->assignRole('teacher');
    $this->plainTeacher = Teacher::create(['user_id' => $teacherUser->id, 'branch_id' => $this->branch->id]);
    $this->teacherUser = $teacherUser;

    $supervisorUser = User::factory()->create(['status' => 'active']);
    $supervisorUser->assignRole('supervisor');
    $this->supervisor = Teacher::create(['user_id' => $supervisorUser->id, 'branch_id' => $this->branch->id]);

    $this->student = Student::create(['name' => 'طالب الحذف', 'center_id' => $this->center->id, 'circle_id' => $this->circle->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
    $this->subscription = Subscription::create(['student_id' => $this->student->id, 'circle_id' => $this->circle->id, 'teacher_id' => $this->admin->id, 'month' => '2026-10-01', 'status' => 'مدفوع', 'amount' => 100, 'payment_method' => 'نقدي', 'paid_at' => now(), 'collected_by' => $this->admin->id]);
});

test('branch with circles or teachers cannot be deleted', function () {
    $this->actingAs($this->admin)->delete(route('branches.destroy', $this->branch))
        ->assertSessionHas('error');

    expect(Branch::find($this->branch->id))->not->toBeNull()
        ->and(Circle::find($this->circle->id))->not->toBeNull()
        ->and(Subscription::find($this->subscription->id))->not->toBeNull()
        ->and(Teacher::find($this->plainTeacher->id)->branch_id)->toBe($this->branch->id);

    // فرع فارغ تمامًا يُحذف بنجاح
    $empty = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع فارغ', 'established_at' => '2020-01-01']);
    $this->actingAs($this->admin)->delete(route('branches.destroy', $empty))
        ->assertSessionHas('success');
    expect(Branch::find($empty->id))->toBeNull();
});

test('center with branches lessons or students cannot be deleted', function () {
    // المركز الرئيسي: فروع وطلاب
    $this->actingAs($this->admin)->delete(route('centers.destroy', $this->center))
        ->assertSessionHas('error');
    expect(Center::find($this->center->id))->not->toBeNull();

    // مركز هيكلي: فرع وحلقة ودرس بلا طلاب — كان يُمسح كله بنقرة
    $c2 = Center::create(['name' => 'مركز هيكلي']);
    $b2 = Branch::create(['center_id' => $c2->id, 'name' => 'فرع هيكلي', 'established_at' => '2020-01-01']);
    $ci2 = Circle::create(['name' => 'حلقة هيكلية', 'type' => 'group', 'level' => 'build', 'branch_id' => $b2->id]);
    $lesson = \App\Models\EducationalLesson::create(['center_id' => $c2->id, 'title' => 'درس', 'created_by' => $this->admin->id]);

    $this->actingAs($this->admin)->delete(route('centers.destroy', $c2))
        ->assertSessionHas('error');
    expect(Center::find($c2->id))->not->toBeNull()
        ->and(Branch::find($b2->id))->not->toBeNull()
        ->and(Circle::find($ci2->id))->not->toBeNull()
        ->and(\App\Models\EducationalLesson::find($lesson->id))->not->toBeNull();

    // مركز فارغ تمامًا يُحذف بنجاح
    $empty = Center::create(['name' => 'مركز فارغ']);
    $this->actingAs($this->admin)->delete(route('centers.destroy', $empty))
        ->assertSessionHas('success');
    expect(Center::find($empty->id))->toBeNull();
});

test('only eligible supervisors can be attached to a branch', function () {
    $branch2 = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع ثانٍ', 'established_at' => '2020-01-01']);
    $circle2 = Circle::create(['name' => 'حلقة ثانية', 'type' => 'group', 'level' => 'build', 'branch_id' => $branch2->id]);

    // معلم عادي: مرفوض ولا يكتسب أي وصول
    $this->actingAs($this->admin)->put(route('branches.update', $branch2), [
        'center_id' => $this->center->id, 'name' => 'فرع ثانٍ',
        'established_at' => '2020-01-01', 'supervisor_ids' => [$this->plainTeacher->id],
    ])->assertSessionHasErrors('supervisor_ids.0');
    expect($branch2->supervisors()->where('teachers.id', $this->plainTeacher->id)->exists())->toBeFalse()
        ->and($this->teacherUser->fresh()->can('view', $circle2))->toBeFalse();

    // مشرف مؤهل: يُقبل
    $this->actingAs($this->admin)->put(route('branches.update', $branch2), [
        'center_id' => $this->center->id, 'name' => 'فرع ثانٍ',
        'established_at' => '2020-01-01', 'supervisor_ids' => [$this->supervisor->id],
    ])->assertSessionHasNoErrors();
    expect($branch2->supervisors()->where('teachers.id', $this->supervisor->id)->exists())->toBeTrue();
});
