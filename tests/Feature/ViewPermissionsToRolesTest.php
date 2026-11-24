<?php

/*
|--------------------------------------------------------------------------
| حذف ثلاث صلاحيات عرض وتحويلها لبوابات أدوار (تقرير 223)
|--------------------------------------------------------------------------
| - view subscriptions chart حُذفت وصار الرسم البياني للإيرادات للأدمن فقط
|   (كان: أدمن + مدير عام + مدير).
| - view student weekly followups حُذفت وصارت بوابة ثابتة للأدوار الخمسة
|   (نفس الحاملين، بلا تغيير في الوصول).
| - view collection rounds حُذفت وصارت بوابة ثابتة (أدمن + مدير + مشرف —
|   نفس الحاملين؛ المدير العام والمعلم كانا خارجها وبقيا خارجها).
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

    $this->center = Center::create(['name' => 'مركز']);
    $this->branch = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع']);
    $this->circle = Circle::create(['name' => 'حلقة', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branch->id]);

    $mk = function (string $role) {
        $u = User::factory()->create(['status' => 'active']);
        $u->assignRole($role);
        Teacher::create(['user_id' => $u->id, 'branch_id' => $this->branch->id]);
        return $u;
    };

    $this->admin = $mk('admin');
    $this->gm = $mk('general_manager');
    $this->manager = $mk('manager');
    $this->supervisor = $mk('supervisor');
    $this->teacher = $mk('teacher');
    $this->guardian = $mk('guardian');
    $this->examiner = $mk('examiner');

    DB::table('branch_teacher')->insert(['branch_id' => $this->branch->id, 'teacher_id' => Teacher::where('user_id', $this->supervisor->id)->value('id'), 'created_at' => now(), 'updated_at' => now()]);

    $student = Student::create(['name' => 'طالب', 'center_id' => $this->center->id, 'circle_id' => $this->circle->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
    Subscription::create(['student_id' => $student->id, 'circle_id' => $this->circle->id, 'teacher_id' => $this->teacher->id, 'month' => now()->format('Y-m-01'), 'status' => 'مدفوع', 'amount' => 100, 'payment_method' => 'نقدي', 'paid_at' => now(), 'collected_by' => $this->teacher->id]);
});

test('رسم إيرادات الاشتراكات للأدمن فقط بعد حذف صلاحيته', function () {
    $adminData = $this->actingAs($this->admin)->get(route('subscriptions.index'))->assertOk()->viewData('monthlyRevenue');
    expect($adminData)->not->toBeNull()->and($adminData->isNotEmpty())->toBeTrue();

    foreach ([$this->gm, $this->manager] as $u) {
        $data = $this->actingAs($u)->get(route('subscriptions.index'))->assertOk()->viewData('monthlyRevenue');
        expect($data === null || $data->isEmpty())->toBeTrue();
    }

    expect(\Spatie\Permission\Models\Permission::where('name', 'view subscriptions chart')->exists())->toBeFalse();
});

test('المتابعة الأسبوعية بوابة خمسة أدوار ثابتة بعد حذف صلاحيتها', function () {
    foreach ([$this->admin, $this->gm, $this->manager, $this->supervisor, $this->teacher] as $u) {
        $this->actingAs($u)->get(route('student-weekly-followups.index-group'))->assertOk();
    }
    foreach ([$this->guardian, $this->examiner] as $u) {
        $this->actingAs($u)->get(route('student-weekly-followups.index-group'))->assertRedirect();
    }

    expect(\Spatie\Permission\Models\Permission::where('name', 'view student weekly followups')->exists())->toBeFalse();
});

test('جولات التحصيل بوابة أدمن ومدير ومشرف بعد حذف صلاحية العرض', function () {
    foreach ([$this->admin, $this->manager, $this->supervisor] as $u) {
        $this->actingAs($u)->get(route('collection-rounds.index'))->assertOk();
    }
    foreach ([$this->gm, $this->teacher, $this->guardian] as $u) {
        $this->actingAs($u)->get(route('collection-rounds.index'))->assertRedirect();
    }

    expect(\Spatie\Permission\Models\Permission::where('name', 'view collection rounds')->exists())->toBeFalse();
});
