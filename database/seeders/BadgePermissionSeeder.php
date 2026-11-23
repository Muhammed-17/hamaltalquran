<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ينشئ صلاحيات نظام الأوسمة ويمنحها للأدوار الموجودة بشكل إضافي
 * (لا يحذف أي صلاحية ولا ينشئ أدوارًا جديدة).
 * المصدر المركزي: config/badge_permissions.php
 * ملحوظة: PermissionSeeder الرئيسي يدمج هذه الصلاحيات في خريطته أيضًا،
 * فإعادة تشغيله لا تحذفها.
 */
class BadgePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = config('badge_permissions.permissions', []);

        foreach ($permissions as $name => $details) {
            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['display_name' => $details[0], 'group' => $details[1]]
            );
        }

        foreach (config('badge_permissions.grants', []) as $roleName => $grants) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                continue; // لا إنشاء أدوار جديدة تلقائيًا
            }

            $names = $grants === ['*'] ? array_keys($permissions) : $grants;
            $role->givePermissionTo($names);
            $this->command?->info("✓ أوسمة: مُنحت صلاحيات الأوسمة لدور [{$roleName}]");
        }

        // التحصيل وظيفة إدارية: تُسحب صلاحياته من دور المعلم حتى لو كانت
        // ممنوحة له في قاعدة البيانات من تشغيل سابق لهذا البذر
        $teacherRole = Role::where('name', 'teacher')->first();
        $teacherRole?->revokePermissionTo([
            'badges.collections.view', 'badges.collections.create', 'badges.collections.update',
            'badges.collections.complete', 'badges.collections.cancel',
        ]);

        // إعادة هيكلة الصلاحيات: أنواع الأوسمة والمخزون المركزي لمدير
        // النظام وحده، وصلاحيات التقارير حُذفت (بوابتها دور الأدمن)،
        // وصلاحيات التوزيعات دُمجت في الاستحقاقات، والتسليمات لا يراها
        // المعلم — تُسحب المنح القديمة من أي دور غير الأدمن صراحة لأن
        // هذا البذر إضافي بطبيعته ولا يسحب تلقائيًا
        $adminOnly = [
            'badges.types.view', 'badges.types.create', 'badges.types.update', 'badges.types.delete',
            'badges.stock.view', 'badges.stock.add', 'badges.stock.adjust', 'badges.stock.history',
            'badges.reports.view',
        ];
        $mergedAway = [
            'badges.distributions.view', 'badges.distributions.create', 'badges.distributions.cancel',
        ];
        foreach (Role::where('name', '!=', 'admin')->get() as $role) {
            $role->revokePermissionTo(array_merge($adminOnly, $mergedAway));
        }
        Role::where('name', 'admin')->first()?->revokePermissionTo($mergedAway);
        $teacherRole?->revokePermissionTo([
            'badges.handovers.view', 'badges.handovers.create', 'badges.handovers.cancel',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
