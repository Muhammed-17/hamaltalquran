<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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

        // بوابات الأدوار حلّت محل عائلات كاملة من الصلاحيات (الأنواع،
        // المخزون، التسليمات، التحصيل، التوزيعات المدموجة، التقارير):
        // تُسحب منحها القديمة من كل الأدوار صراحة لأن هذا البذر إضافي
        // بطبيعته ولا يسحب تلقائيًا — وتبقى صفوف الصلاحيات اليتيمة في
        // القاعدة بلا أثر (لا فحص في الكود يشير إليها)
        $roleGated = [
            'badges.types.view', 'badges.types.create', 'badges.types.update', 'badges.types.delete',
            'badges.stock.view', 'badges.stock.add', 'badges.stock.adjust', 'badges.stock.return', 'badges.stock.history',
            'badges.handovers.view', 'badges.handovers.create', 'badges.handovers.cancel',
            'badges.collections.view', 'badges.collections.create', 'badges.collections.update', 'badges.collections.complete', 'badges.collections.cancel',
            'badges.distributions.view', 'badges.distributions.create', 'badges.distributions.cancel',
            'badges.reports.view',
        ];
        foreach (Role::all() as $role) {
            $role->revokePermissionTo($roleGated);
        }

        // حذف صفوف الصلاحيات اليتيمة نفسها: صفحة إدارة الأدوار تقرأ جدول
        // الصلاحيات، فبقاء الصف يعني بقاء خانة اختيار قابلة للمنح رغم أن
        // لا فحص في الكود يشير إليها. يُحذف كل صف أوسمة غير معرّف في
        // الإعدادات الحالية، مع روابطه بالأدوار والمستخدمين أولًا
        $orphanIds = Permission::where('name', 'like', 'badges.%')
            ->whereNotIn('name', array_keys($permissions))
            ->pluck('id');
        if ($orphanIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $orphanIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $orphanIds)->delete();
            Permission::whereIn('id', $orphanIds)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
