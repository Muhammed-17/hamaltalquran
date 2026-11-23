<?php

/*
|--------------------------------------------------------------------------
| صلاحيات نظام الأوسمة — المصدر المركزي الوحيد
|--------------------------------------------------------------------------
| تُستخدم هذه القائمة في:
| - BadgePermissionSeeder (إنشاء الصلاحيات ومنحها للأدوار)
| - PermissionSeeder (يُدمجها في خريطته حتى لا يحذفها عند إعادة التشغيل)
| - Policies و Routes و Blade عبر الثوابت النصية نفسها
|
| البنية: 'اسم الصلاحية' => ['الاسم العربي', 'المجموعة']
| و 'grants': الدور => قائمة الصلاحيات الممنوحة افتراضيًا
|
| ملحوظة: أغلب جزئية الأوسمة لا يعمل بصلاحيات أصلًا بل ببوابات أدوار:
| الأنواع والمخزون المركزي والتقارير والإحصائيات لمدير النظام وحده،
| والتسليمات والتحصيل للإدارة (مدير عام/مدير فرع/مشرف)، وإرجاع العهدة
| لأي دور من أدوار العاملين الخمسة. الصلاحيات المعرّفة هنا هي دورة
| العمل اليومية المشتركة: الاستحقاقات والتوزيعات والنواقص.
*/

return [

    'permissions' => [
        // استحقاقات الطلاب وتوزيعاتهم (صفحة واحدة — صلاحيات واحدة:
        // التوزيع يعمل بصلاحيات الاستحقاقات منذ توحيد الصفحة)
        'badges.entitlements.view'    => ['عرض استحقاقات الأوسمة', 'badges'],
        'badges.entitlements.create'  => ['تسجيل استحقاقات الأوسمة', 'badges'],
        'badges.entitlements.update'  => ['تعديل استحقاقات الأوسمة', 'badges'],
        'badges.entitlements.cancel'  => ['إلغاء استحقاقات الأوسمة', 'badges'],

        // الأوسمة الناقصة
        'badges.shortages.view'       => ['عرض الأوسمة الناقصة', 'badges'],
        'badges.shortages.resolve'    => ['استكمال الأوسمة الناقصة', 'badges'],
    ],

    /*
    | المنح الافتراضية للأدوار الموجودة (لا تُنشأ أدوار جديدة).
    | مدير النظام يأخذ كل الصلاحيات المعرّفة. بقية الأدوار تعمل في
    | دورة الأوسمة اليومية داخل نطاقها: استحقاقات وتوزيعات ونواقص —
    | والباقي (أنواع/مخزون/تسليمات/تحصيل/تقارير/إحصائيات) بوابات
    | أدوار لا صلاحيات، فلا يظهر في هذه القائمة.
    */
    'grants' => [
        'admin' => ['*'],

        'general_manager' => [
            'badges.entitlements.view', 'badges.entitlements.create', 'badges.entitlements.update', 'badges.entitlements.cancel',
            'badges.shortages.view', 'badges.shortages.resolve',
        ],

        'manager' => [
            'badges.entitlements.view', 'badges.entitlements.create', 'badges.entitlements.update', 'badges.entitlements.cancel',
            'badges.shortages.view', 'badges.shortages.resolve',
        ],

        'supervisor' => [
            'badges.entitlements.view', 'badges.entitlements.create', 'badges.entitlements.update', 'badges.entitlements.cancel',
            'badges.shortages.view', 'badges.shortages.resolve',
        ],

        'teacher' => [
            'badges.entitlements.view', 'badges.entitlements.create',
            'badges.shortages.view', 'badges.shortages.resolve',
        ],
    ],
];
