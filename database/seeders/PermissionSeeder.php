<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // مسح الكاش لتفادي تداخل البيانات القديمة
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // هيكلة الصلاحيات: [ الصلاحية بالإنجليزية => [الاسم العربي, المجموعة] ]
        $permissionMap = [
            // ─────────────────────────────────────────────────────────
            // النظام واللوحة (system)
            // ─────────────────────────────────────────────────────────
            'edit profile'                => ['تعديل الملف الشخصي', 'system'],
            'view favorites'              => ['عرض المفضلة', 'students'],

            // ─────────────────────────────────────────────────────────
            // الحلقات (circles)
            // ─────────────────────────────────────────────────────────
            'edit circles'                => ['تعديل الحلقات', 'circles'],

            // ─────────────────────────────────────────────────────────
            // الطلاب (students)
            // ─────────────────────────────────────────────────────────
            'create student'              => ['تسجيل طالب جديد', 'students'],
            'edit student'                => ['تعديل بيانات الطالب', 'students'],
            // ─────────────────────────────────────────────────────────
            // الحضور والغياب (attendance)
            // ─────────────────────────────────────────────────────────
            'notify attendance'           => ['إشعار أولياء الأمور بالغياب', 'attendance'],
            'view sequential absences'   => ['عرض الغيابات المتكررة', 'attendance'],

            // ─────────────────────────────────────────────────────────
            // الاشتراكات والمالية (subscriptions)
            // ─────────────────────────────────────────────────────────
            'view late and unpaid'        => ['عرض الطلاب المتعثرين', 'subscriptions'],
            'create subscriptions'        => ['تسجيل دفع اشتراك', 'subscriptions'],
            'edit subscriptions'          => ['تعديل الاشتراكات', 'subscriptions'],
            'delete subscriptions'        => ['حذف سندات الاشتراكات', 'subscriptions'],
            'view subscriptions chart'    => ['عرض رسوم الاشتراكات', 'subscriptions'],
            'notify unpaid subscriptions' => ['إشعار أولياء الأمور بالاشتراكات المتأخرة', 'subscriptions'],
            'transfer subscriptions'      => ['تحويل الاشتراكات', 'subscriptions'],

            // ─────────────────────────────────────────────────────────
            // التحصيلات (collection rounds)
            // ─────────────────────────────────────────────────────────
            'view collection rounds'      => ['عرض التحصيل', 'collection rounds'],
            'create collection rounds'    => ['إنشاء تحصيل', 'collection rounds'],
            'edit collection rounds'      => ['تعديل التحصيل', 'collection rounds'],
            'delete collection rounds'    => ['حذف التحصيل', 'collection rounds'],
            'confirm collection rounds'   => ['تأكيد التحصيل', 'collection rounds'],

            // ─────────────────────────────────────────────────────────
            // متابعة الطلاب الأسبوعية (student weekly followups)
            // ─────────────────────────────────────────────────────────
            'view student weekly followups'     => ['عرض متابعة الطلاب الأسبوعية', 'student weekly followups'],
            'create student weekly followups'   => ['إنشاء متابعة الطلاب الأسبوعية', 'student weekly followups'],
            'edit student weekly followups'     => ['تعديل متابعة الطلاب الأسبوعية', 'student weekly followups'],
            'delete student weekly followups'   => ['حذف متابعة الطلاب الأسبوعية', 'student weekly followups'],

            // ─────────────────────────────────────────────────────────
            // خطط الجلسات الجماعية (group session plans)
            // ─────────────────────────────────────────────────────────
            'view group session plans'    => ['عرض خطط الجلسات الجماعية', 'group session plans'],
            'edit group session plans'    => ['تعديل خطط الجلسات الجماعية', 'group session plans'],
            'delete group session plans'  => ['حذف خطط الجلسات الجماعية', 'group session plans'],

            // ─────────────────────────────────────────────────────────
            // الملاحظات السلوكية (behavioral notes)
            // ─────────────────────────────────────────────────────────
            'view behavioral notes'       => ['عرض الملاحظات السلوكية', 'behavioral notes'],
            'create behavioral notes'     => ['إنشاء ملاحظات سلوكية', 'behavioral notes'],
            'edit behavioral notes'       => ['تعديل الملاحظات السلوكية', 'behavioral notes'],
            'delete behavioral notes'     => ['حذف الملاحظات السلوكية', 'behavioral notes'],
            'approve behavioral notes'    => ['اعتماد الملاحظات السلوكية', 'behavioral notes'],

            // ─────────────────────────────────────────────────────────
            // اختبارات السور (surah tests)
            // ─────────────────────────────────────────────────────────
            'view surah tests'            => ['عرض الاختبارات', 'surah tests'],
            'create surah tests'          => ['إنشاء اختبارات', 'surah tests'],
            'update surah tests'          => ['تعديل الاختبارات', 'surah tests'],
            'delete surah tests'          => ['حذف الاختبارات', 'surah tests'],
            'edit surah test percentages' => ['تعديل نسبة الاختبارات', 'surah tests'],

            // ─────────────────────────────────────────────────────────
            // المسابقات (competitions)
            // ─────────────────────────────────────────────────────────
            'view competitions'   => ['عرض المسابقات', 'competitions'],
            'create competitions' => ['إنشاء مسابقات', 'competitions'],
            'edit competitions'   => ['تعديل المسابقات', 'competitions'],
            'delete competitions' => ['حذف المسابقات', 'competitions'],
            'manage competitions' => ['إدارة المسابقات', 'competitions'],
            'view level questions' => ['عرض أسئلة المستوى', 'competitions'],
            'create level questions' => ['إنشاء أسئلة المستوى', 'competitions'],
            'edit level questions' => ['تعديل أسئلة المستوى', 'competitions'],
            'delete level questions' => ['حذف أسئلة المستوى', 'competitions'],
            'view competition examiners' => ['عرض مختبرين للمسابقة', 'competitions'],
            'select competition examiners' => ['إختيار مختبرين للمسابقة', 'competitions'],
            'edit examiner levels' => ['تعديل مستويات للمختبر', 'competitions'],
            'delete competition examiners' => ['حذف المختبرين  من مسابقة', 'competitions'],
            'select examiner questions' => ['إختيار أسئلة المختبر', 'competitions'],
            
            // ─────────────────────────────────────────────────────────
            // مشاركو المسابقات (competition participants)
            // ─────────────────────────────────────────────────────────
            'view competition participants'    => ['عرض مُشارِكي المسابقة', 'competitions'],
            'create competition participants'  => ['إضافة مُشارِكين للمسابقة', 'competitions'],
            'edit competition participants'    => ['تعديل بيانات مُشارِكي المسابقة', 'competitions'],
            'delete competition participants'  => ['حذف مُشارِكي المسابقة', 'competitions'],
            'export competition participants'  => ['تصدير بيانات مُشارِكي المسابقة', 'competitions'],
            'examine competition participants' => ['اختبار/تقييم المُشارِكين', 'competitions'],
        ];

        // دمج صلاحيات نظام الأوسمة من مصدرها المركزي (config/badge_permissions.php)
        // حتى لا يحذفها خطوة التنظيف بالأسفل عند إعادة تشغيل هذا الـ Seeder
        foreach (config('badge_permissions.permissions', []) as $name => $details) {
            $permissionMap[$name] = $details;
        }

        // 1. إنشاء وتحديث الصلاحيات ببياناتها العربية الكاملة والمجمعات
        foreach ($permissionMap as $name => $details) {
            Permission::updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $details[0],
                    'group'        => $details[1],
                    'guard_name'   => 'web'
                ]
            );
        }

        // 2. حذف أي صلاحية موجودة في قاعدة البيانات لكن لم تعد موجودة في الكود
        Permission::whereNotIn('name', array_keys($permissionMap))->delete();

        // 3. مصفوفة ربط الأدوار بالصلاحيات الخاصة بها
        $rolesPermissions = [
            'admin' => [
                // system
                'view favorites',
                'view sequential absences',
                'edit profile',
                // circles
                'edit circles',
                // students
                'create student',
                'edit student',
                // attendance
                'notify attendance',
                // subscriptions
                'view late and unpaid',
                'create subscriptions',
                'edit subscriptions',
                'delete subscriptions',
                'notify unpaid subscriptions',
                'transfer subscriptions',
                'view subscriptions chart',
                // collection rounds
                'view collection rounds',
                'create collection rounds',
                'edit collection rounds',
                'delete collection rounds',
                'confirm collection rounds',
                // group session plans
                'view group session plans',
                'edit group session plans',
                'delete group session plans',
                // student weekly followups
                'view student weekly followups',
                'create student weekly followups',
                'edit student weekly followups',
                'delete student weekly followups',
                // behavioral notes
                'view behavioral notes',
                'create behavioral notes',
                'edit behavioral notes',
                'delete behavioral notes',
                'approve behavioral notes',
                // guardians

                // surah tests
                'view surah tests',
                'create surah tests',
                'update surah tests',
                'delete surah tests',
                'edit surah test percentages',

                // competitions & questions
                'view competitions',
                'create competitions',
                'edit competitions',
                'delete competitions',
                'manage competitions',
                'view level questions',
                'create level questions',
                'edit level questions',
                'delete level questions',

                // competition examiners
                'view competition examiners',
                'select competition examiners',
                'edit examiner levels',
                'delete competition examiners',
                'select examiner questions',

                // competition participants
                'view competition participants',
                'create competition participants',
                'edit competition participants',
                'delete competition participants',
                'export competition participants',
                'examine competition participants',
            ],

            'general_manager' => [
                // system
                'view favorites',
                'view sequential absences',
                'edit profile',
                // circles
                'edit circles',
                // students
                // attendance
                'notify attendance',
                // subscriptions
                'view late and unpaid',
                'create subscriptions',
                'edit subscriptions',
                'delete subscriptions',
                'transfer subscriptions',
                'notify unpaid subscriptions',
                'view subscriptions chart',
                // group session plans
                'view group session plans',
                'edit group session plans',
                'delete group session plans',
                // student weekly followups
                'view student weekly followups',
                'create student weekly followups',
                'edit student weekly followups',
                'delete student weekly followups',
                // behavioral notes
                'view behavioral notes',
                'create behavioral notes',
                'edit behavioral notes',
                'delete behavioral notes',
                'approve behavioral notes',
                // guardians

                // surah tests
                'view surah tests',
                'create surah tests',
                'update surah tests',
                'delete surah tests',

                // competitions
                'view competitions',

                // competition participants
                'view competition participants',
            ],

            'manager' => [
                // system
                'view favorites',
                'view sequential absences',
                'edit profile',
                // circles
                'edit circles',
                // students
                // attendance
                'notify attendance',
                // subscriptions
                'view late and unpaid',
                'create subscriptions',
                'edit subscriptions',
                'delete subscriptions',
                'notify unpaid subscriptions',
                'transfer subscriptions',
                'view subscriptions chart',
                // collection rounds
                'create collection rounds',
                'view collection rounds',
                'edit collection rounds',
                'delete collection rounds',
                'confirm collection rounds',
                // group session plans
                'view group session plans',
                'edit group session plans',
                'delete group session plans',
                // student weekly followups
                'view student weekly followups',
                'create student weekly followups',
                'edit student weekly followups',
                'delete student weekly followups',
                // behavioral notes
                'view behavioral notes',
                'create behavioral notes',
                'edit behavioral notes',
                'delete behavioral notes',
                'approve behavioral notes',
                // guardians

                // surah tests
                'view surah tests',
                'create surah tests',
                'update surah tests',
                'delete surah tests',

                // competitions
                'view competitions',

                // competition participants
                'view competition participants',
            ],

            'supervisor' => [
                // system
                'view favorites',
                'view sequential absences',
                'edit profile',
                // circles
                'edit circles',
                // students
                // attendance
                // subscriptions
                'view late and unpaid',
                'create subscriptions',
                'edit subscriptions',
                'delete subscriptions',
                'transfer subscriptions',
                // collection rounds
                'create collection rounds',
                'view collection rounds',
                'confirm collection rounds',
                // group session plans
                'view group session plans',
                'edit group session plans',
                'delete group session plans',
                // student weekly followups
                'view student weekly followups',
                'create student weekly followups',
                'edit student weekly followups',
                'delete student weekly followups',
                // behavioral notes
                'view behavioral notes',
                'create behavioral notes',
                'edit behavioral notes',
                'delete behavioral notes',
                'approve behavioral notes',

                // surah tests
                'view surah tests',
                'create surah tests',
                'update surah tests',
                'delete surah tests',

                // competitions
                'view competitions',
            ],

            'teacher' => [
                // system
                'view favorites',
                'view sequential absences',
                'edit profile',
                // students
                // circles
                // attendance
                // subscriptions
                'view late and unpaid',
                'create subscriptions',
                'edit subscriptions',
                'delete subscriptions',
                // group session plans
                'view group session plans',
                // student weekly followups
                'view student weekly followups',
                'create student weekly followups',
                'edit student weekly followups',
                'delete student weekly followups',
                // behavioral notes
                'view behavioral notes',
                'create behavioral notes',
                'edit behavioral notes',
                'delete behavioral notes',
            ],

            'examiner' => [],

            'guardian' => [],
        ];

        // مسميات الأدوار العربية لـ display_name المتواجد بجدول الـ roles
        $roleDisplayNames = [
            'admin'           => 'مدير النظام',
            'general_manager' => 'مدير عام',
            'manager'         => 'مدير فرع',
            'supervisor'      => 'مشرف',
            'teacher'         => 'معلم',
            'examiner'       => 'مختبر',
            'guardian'        => 'ولي أمر',
        ];

        // دمج منح الأوسمة الافتراضية في صلاحيات كل دور
        $badgePermissionNames = array_keys(config('badge_permissions.permissions', []));
        foreach (config('badge_permissions.grants', []) as $roleName => $grants) {
            if ($grants === ['*']) {
                $grants = $badgePermissionNames;
            }
            $rolesPermissions[$roleName] = array_values(array_unique(array_merge(
                $rolesPermissions[$roleName] ?? [],
                $grants
            )));
        }

        // 3. إنشاء وتحديث الأدوار ومزامنة صلاحياتها
        foreach ($rolesPermissions as $roleName => $rolePermissions) {
            $role = Role::updateOrCreate(
                ['name' => $roleName],
                [
                    'display_name' => $roleDisplayNames[$roleName] ?? $roleName,
                    'guard_name'   => 'web'
                ]
            );

            // مزامنة الصلاحيات المحددة لهذا الدور
            $role->syncPermissions($rolePermissions);

            $this->command->info("✓ تم ضبط الدور [{$roleDisplayNames[$roleName]}] ومزامنة (" . count($rolePermissions) . ") صلاحية بنجاح.");
        }

        // مسح كاش الصلاحيات النهائي ليعمل النظام فوراً بالإعدادات الجديدة
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->command->info('✅ تم تحديث وهيكلة جدول الصلاحيات والأدوار (display_name & group) بنجاح كامل.');
    }
}
