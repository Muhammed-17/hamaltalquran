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
            'manage guardians'             => ['إدارة حسابات أولياء الأمور', 'system'],
            'edit profile'                => ['تعديل الملف الشخصي', 'system'],
            'view reports'                => ['عرض التقارير', 'system'],
            'export data'                 => ['تصدير البيانات', 'system'],

            // ─────────────────────────────────────────────────────────
            // المستخدمين (users)
            // ─────────────────────────────────────────────────────────
            'view users'                  => ['عرض المستخدمين', 'users'],
            'create users'                => ['إضافة مستخدمين', 'users'],
            'edit users'                  => ['تعديل المستخدمين', 'users'],
            'delete users'                => ['حذف المستخدمين', 'users'],
            'manage roles'                => ['إدارة الأدوار والصلاحيات', 'users'],

            // ─────────────────────────────────────────────────────────
            // الحلقات (circles)
            // ─────────────────────────────────────────────────────────
            'view circles'                => ['عرض الحلقات', 'circles'],
            'create circles'              => ['إنشاء حلقات جديدة', 'circles'],
            'edit circles'                => ['تعديل الحلقات', 'circles'],
            'delete circles'              => ['حذف الحلقات', 'circles'],

            // ─────────────────────────────────────────────────────────
            // المعلمين (teachers)
            // ─────────────────────────────────────────────────────────
            'view teachers'               => ['عرض المعلمين', 'teachers'],
            'create teachers'             => ['إضافة معلمين', 'teachers'],
            'edit teachers'               => ['تعديل بيانات المعلمين', 'teachers'],
            'delete teachers'             => ['حذف معلمين', 'teachers'],
            'toggle teacher status'       => ['تنشيط / إيقاف المعلم', 'teachers'],

            // ─────────────────────────────────────────────────────────
            // الطلاب (students)
            // ─────────────────────────────────────────────────────────
            'view students'               => ['عرض الطلاب', 'students'],
            'create students'             => ['إضافة طلاب', 'students'],
            'edit students'               => ['تعديل بيانات الطلاب', 'students'],
            'delete students'             => ['حذف طلاب', 'students'],
            'assign student to circle'    => ['تسكين الطالب في حلقة', 'students'],
            'manage student status'       => ['تحديث حالة الطالب', 'students'],
            // ─────────────────────────────────────────────────────────
            // الحضور والغياب (attendance)
            // ─────────────────────────────────────────────────────────
            'view attendance'             => ['عرض سجلات الحضور', 'attendance'],
            'create attendance'           => ['تسجيل حضور وغياب', 'attendance'],
            'edit attendance'             => ['تعديل الحضور والغياب', 'attendance'],
            'delete attendance'           => ['حذف الحضور والغياب', 'attendance'],
            'notify attendance'           => ['إشعار أولياء الأمور بالغياب', 'attendance'],
            'view own attendance'         => ['عرض حضور الأبناء الشخصي', 'attendance'],

            // ─────────────────────────────────────────────────────────
            // الاشتراكات والمالية (subscriptions)
            // ─────────────────────────────────────────────────────────
            'view subscriptions'          => ['عرض الاشتراكات والمدفوعات', 'subscriptions'],
            'create subscriptions'        => ['تسجيل دفع اشتراك', 'subscriptions'],
            'delete subscriptions'        => ['حذف سندات الاشتراكات', 'subscriptions'],
            'view subscriptions chart'    => ['عرض رسوم الاشتراكات', 'subscriptions'],
            'notify unpaid subscriptions' => ['إشعار أولياء الأمور بالاشتراكات المتأخرة', 'subscriptions'],
            'transfer subscriptions'      => ['تحويل الاشتراكات', 'subscriptions'],
            'manage subscription prices'  => ['إدارة وتحديد أسعار الاشتراكات', 'subscriptions'],
            'view subscription prices'    => ['عرض أسعار الاشتراكات', 'subscriptions'],
            'view own subscriptions'      => ['عرض مدفوعات الأبناء الشخصية', 'subscriptions'],

            // ─────────────────────────────────────────────────────────
            // التحصيلات (collection rounds)
            // ─────────────────────────────────────────────────────────
            'view collection rounds'      => ['عرض التحصيل', 'collection rounds'],
            'create collection rounds'    => ['إنشاء تحصيل', 'collection rounds'],
            'edit collection rounds'      => ['تعديل التحصيل', 'collection rounds'],
            'delete collection rounds'    => ['حذف التحصيل', 'collection rounds'],
            'confirm collection rounds'   => ['تأكيد التحصيل', 'collection rounds'],

            // ─────────────────────────────────────────────────────────
            // أولياء الأمور (guardians)
            // ─────────────────────────────────────────────────────────
            'view own children'           => ['عرض تفاصيل الأبناء', 'guardians'],
            'view notifications'          => ['عرض الإشعارات', 'guardians'],

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
            'create group session plans'  => ['إنشاء خطط الجلسات الجماعية', 'group session plans'],
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
                'manage guardians',
                'edit profile',
                'view reports',
                'export data',
                // users
                'view users',
                'create users',
                'edit users',
                'delete users',
                'manage roles',
                // circles
                'view circles',
                'create circles',
                'edit circles',
                'delete circles',
                // teachers
                'view teachers',
                'create teachers',
                'edit teachers',
                'delete teachers',
                'toggle teacher status',
                // students
                'view students',
                'create students',
                'edit students',
                'delete students',
                'assign student to circle',
                'manage student status',
                // attendance
                'view attendance',
                'create attendance',
                'edit attendance',
                'delete attendance',
                'notify attendance',
                // subscriptions
                'view subscriptions',
                'create subscriptions',
                'delete subscriptions',
                'notify unpaid subscriptions',
                'transfer subscriptions',
                'manage subscription prices',
                'view subscription prices',
                'view subscriptions chart',
                // collection rounds
                'view collection rounds',
                'create collection rounds',
                'edit collection rounds',
                'delete collection rounds',
                'confirm collection rounds',
                // group session plans
                'view group session plans',
                'create group session plans',
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
                'view notifications',

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
                'manage guardians',
                'edit profile',
                'view reports',
                'export data',
                // users
                'view users',
                // circles
                'view circles',
                'edit circles',
                // teachers
                'view teachers',
                // students
                'view students',
                'assign student to circle',
                'manage student status',
                // attendance
                'view attendance',
                'create attendance',
                'edit attendance',
                'delete attendance',
                'notify attendance',
                // subscriptions
                'view subscriptions',
                'create subscriptions',
                'delete subscriptions',
                'transfer subscriptions',
                'notify unpaid subscriptions',
                'view subscriptions chart',
                // group session plans
                'view group session plans',
                'create group session plans',
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
                'view notifications',

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
                'edit profile',
                'manage guardians',
                // circles
                'view circles',
                'edit circles',
                // teachers
                'view teachers',
                // students
                'view students',
                'create students',
                'edit students',
                'assign student to circle',
                'manage student status',
                // attendance
                'view attendance',
                'create attendance',
                'edit attendance',
                'delete attendance',
                'notify attendance',
                // subscriptions
                'view subscriptions',
                'create subscriptions',
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
                'create group session plans',
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
                'view notifications',

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
                'edit profile',
                // circles
                'view circles',
                'edit circles',
                // students
                'view students',
                'assign student to circle',
                // attendance
                'view attendance',
                'create attendance',
                'edit attendance',
                // subscriptions
                'view subscriptions',
                'create subscriptions',
                'delete subscriptions',
                'transfer subscriptions',
                // collection rounds
                'create collection rounds',
                'view collection rounds',
                'confirm collection rounds',
                // group session plans
                'view group session plans',
                'create group session plans',
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
                'edit profile',
                // students
                'view students',
                // circles
                'view circles',
                // attendance
                'view attendance',
                'create attendance',
                // subscriptions
                'view subscriptions',
                'create subscriptions',
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

            'guardian' => [
                // system
                'edit profile',
                // guardians
                'view own children',
                'view own attendance',
                'view own subscriptions',
                'view notifications',
            ],
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
