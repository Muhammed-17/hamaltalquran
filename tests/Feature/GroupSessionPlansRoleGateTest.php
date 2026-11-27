<?php

/*
|--------------------------------------------------------------------------
| حذف صلاحيات خطط الجلسات الجماعية الثلاث وتحويلها لأدوار (تقرير 225)
|--------------------------------------------------------------------------
| view/edit/delete group session plans حُذفت من الكتالوج، وصارت:
| العرض بوابة الأدوار الخمسة، والتعديل والحذف بوابة الأربعة الإشرافية —
| نفس الحاملين تمامًا، فالوصول لم يتغير لأي دور.
*/

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\GroupSessionPlan;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['center_id' => $center->id, 'name' => 'فرع']);
    $this->circle = Circle::create(['name' => 'حلقة', 'type' => 'group', 'level' => 'build', 'branch_id' => $branch->id]);

    $mk = function (string $role) use ($branch) {
        $u = User::factory()->create(['status' => 'active']);
        $u->assignRole($role);
        $t = Teacher::create(['user_id' => $u->id, 'branch_id' => $branch->id]);
        return [$u, $t];
    };

    [$this->admin] = $mk('admin');
    [$this->gm] = $mk('general_manager');
    [$this->manager] = $mk('manager');
    [$this->supervisor, $supT] = $mk('supervisor');
    [$this->teacher, $teaT] = $mk('teacher');
    [$this->guardian] = $mk('guardian');

    DB::table('branch_teacher')->insert(['branch_id' => $branch->id, 'teacher_id' => $supT->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circle->id, 'teacher_id' => $teaT->id, 'role' => 'main', 'created_at' => now(), 'updated_at' => now()]);

    $this->plan = GroupSessionPlan::create(['circle_id' => $this->circle->id, 'session_name' => 'جلسة', 'start_time' => '16:00', 'end_time' => '17:00', 'planned_content' => 'محتوى']);
});

test('الصلاحيات الثلاث لم تعد موجودة والعرض للأدوار الخمسة فقط', function () {
    foreach (['view group session plans', 'edit group session plans', 'delete group session plans'] as $name) {
        expect(Permission::where('name', $name)->exists())->toBeFalse();
    }

    foreach ([$this->admin, $this->gm, $this->manager, $this->supervisor, $this->teacher] as $u) {
        $this->actingAs($u)->get(route('group-session-plans.index'))->assertOk();
    }
    $this->actingAs($this->guardian)->get(route('group-session-plans.index'))->assertRedirect();
});

test('التعديل والحذف للأربعة الإشرافية: المعلم يرى الخطة ولا يعدلها ولا يحذفها', function () {
    $this->actingAs($this->supervisor)->get(route('group-session-plans.edit', $this->plan))->assertOk();

    $this->actingAs($this->teacher)->get(route('group-session-plans.edit', $this->plan))->assertRedirect();
    $this->actingAs($this->teacher)->delete(route('group-session-plans.destroy', $this->plan))->assertRedirect();
    expect(GroupSessionPlan::find($this->plan->id))->not->toBeNull();
});
