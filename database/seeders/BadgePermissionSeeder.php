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

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
