<?php

/*
|--------------------------------------------------------------------------
| إخفاء دوري مدير النظام والمختبر من صفحة الصلاحيات (بقراره)
|--------------------------------------------------------------------------
| الدوران لا تُدار صلاحياتهما من هذه الصفحة أصلًا: مدير النظام صلاحياته
| كاملة بحكم الدور، والمختبر لا يملك أي صلاحية (بوابته بالدور والإسناد).
| أُضيفا إلى UNMANAGED_ROLES بجانب ولي الأمر: لا يظهران في الصفحة،
| وتعديل صلاحياتهما بطلب مباشر مرفوض، وحذف دور المختبر صار محميًا
| كالأدوار الأساسية. الدوران نفسهما وبقية النظام لم يتغيرا.
*/

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');
});

test('صفحة الصلاحيات لا تعرض دوري مدير النظام والمختبر وتعرض بقية الأدوار', function () {
    $page = $this->actingAs($this->admin)->get(route('admin.roles.index'))->assertOk();

    foreach (['admin', 'examiner', 'guardian'] as $hidden) {
        $role = Role::where('name', $hidden)->firstOrFail();
        $page->assertDontSee(route('admin.roles.permissions.update', $role->id), false);
    }

    foreach (['general_manager', 'manager', 'supervisor', 'teacher'] as $visible) {
        $role = Role::where('name', $visible)->firstOrFail();
        $page->assertSee(route('admin.roles.permissions.update', $role->id), false);
    }
});

test('تعديل صلاحيات مدير النظام أو المختبر بطلب مباشر مرفوض ولا يغيّر شيئًا', function () {
    foreach (['admin', 'examiner'] as $name) {
        $role = Role::where('name', $name)->firstOrFail();
        $before = $role->permissions->pluck('id')->sort()->values()->all();

        $this->actingAs($this->admin)->put(route('admin.roles.permissions.update', $role->id), [
            'permissions' => [],
        ])->assertRedirect(route('admin.roles.index'))->assertSessionHas('error');

        expect($role->fresh()->permissions->pluck('id')->sort()->values()->all())->toBe($before);
    }
});

test('دور المختبر صار محميًا من الحذف كالأدوار الأساسية وبقية الأدوار تُحذف كالمعتاد', function () {
    $examinerRole = Role::where('name', 'examiner')->firstOrFail();
    $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $examinerRole->id))
        ->assertRedirect(route('admin.roles.index'))->assertSessionHas('error');
    expect(Role::where('name', 'examiner')->exists())->toBeTrue();

    // دور مخصص بلا مستخدمين ما زال قابلًا للحذف
    $custom = Role::create(['name' => 'custom_role', 'guard_name' => 'web']);
    $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $custom->id))
        ->assertRedirect(route('admin.roles.index'));
    expect(Role::where('name', 'custom_role')->exists())->toBeFalse();
});
