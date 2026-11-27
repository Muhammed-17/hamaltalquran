<?php

/*
|--------------------------------------------------------------------------
| استرجاع صلاحية view subscriptions (تقرير 224)
|--------------------------------------------------------------------------
| حُذفت في تقرير 213 وحلّت محلها بوابة أدوار خمسة، ثم أمر صاحب الموقع
| باسترجاعها: عادت للكتالوج وممنوحة للأدوار الخمسة نفسها
| (أدمن، مدير عام، مدير، مشرف، معلم)، وعادت كل البوابات تعمل بها:
| المسارات، سياسة الاشتراكات، فروع المتحكم، ورابط الشريط الجانبي.
| رسم الإيرادات يبقى للأدمن فقط (تقرير 223 لم يُمس).
*/

use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('الصلاحية عادت للكتالوج وممنوحة للأدوار الخمسة', function () {
    expect(Permission::where('name', 'view subscriptions')->exists())->toBeTrue();

    foreach (['admin', 'general_manager', 'manager', 'supervisor', 'teacher'] as $role) {
        expect(Role::findByName($role)->hasPermissionTo('view subscriptions'))->toBeTrue();
    }
    foreach (['guardian', 'examiner'] as $role) {
        expect(Role::findByName($role)->hasPermissionTo('view subscriptions'))->toBeFalse();
    }
});

test('البوابة بالصلاحية فعلًا: دور مخصص بها وحدها يفتح الاشتراكات، وبدونها لا', function () {
    Role::create(['name' => 'sub_viewer'])->givePermissionTo('view subscriptions');

    $viewer = User::factory()->create(['status' => 'active']);
    $viewer->assignRole('sub_viewer');
    $this->actingAs($viewer)->get(route('subscriptions.index'))->assertOk();

    $plain = User::factory()->create(['status' => 'active']);
    $plain->assignRole('examiner');
    Teacher::create(['user_id' => $plain->id, 'branch_id' => null]);
    $this->actingAs($plain)->get(route('subscriptions.index'))->assertRedirect();
});
