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
*/

return [

    'permissions' => [
        // أنواع الأوسمة
        'badges.types.view'           => ['عرض أنواع الأوسمة', 'badges'],
        'badges.types.create'         => ['إضافة أنواع الأوسمة', 'badges'],
        'badges.types.update'         => ['تعديل أنواع الأوسمة', 'badges'],
        'badges.types.delete'         => ['حذف أنواع الأوسمة', 'badges'],

        // المخزون المركزي
        'badges.stock.view'           => ['عرض مخزون الأوسمة', 'badges'],
        'badges.stock.add'            => ['إضافة مخزون أوسمة', 'badges'],
        'badges.stock.adjust'         => ['تسوية مخزون الأوسمة', 'badges'],
        'badges.stock.return'         => ['إرجاع أوسمة للإدارة', 'badges'],
        'badges.stock.history'        => ['عرض سجل حركة مخزون الأوسمة', 'badges'],

        // تسليم الأوسمة للمعلمين
        'badges.handovers.view'       => ['عرض تسليمات الأوسمة للمعلمين', 'badges'],
        'badges.handovers.create'     => ['تسليم أوسمة للمعلمين', 'badges'],
        'badges.handovers.cancel'     => ['إلغاء تسليم أوسمة للمعلمين', 'badges'],

        // استحقاقات الطلاب وتوزيعاتهم (صفحة واحدة — صلاحيات واحدة:
        // التوزيع يعمل بصلاحيات الاستحقاقات منذ توحيد الصفحة)
        'badges.entitlements.view'    => ['عرض استحقاقات الأوسمة', 'badges'],
        'badges.entitlements.create'  => ['تسجيل استحقاقات الأوسمة', 'badges'],
        'badges.entitlements.update'  => ['تعديل استحقاقات الأوسمة', 'badges'],
        'badges.entitlements.cancel'  => ['إلغاء استحقاقات الأوسمة', 'badges'],

        // الأوسمة الناقصة
        'badges.shortages.view'       => ['عرض الأوسمة الناقصة', 'badges'],
        'badges.shortages.resolve'    => ['استكمال الأوسمة الناقصة', 'badges'],

        // تحصيل الأوسمة
        'badges.collections.view'     => ['عرض جولات تحصيل الأوسمة', 'badges'],
        'badges.collections.create'   => ['إنشاء جولات تحصيل الأوسمة', 'badges'],
        'badges.collections.update'   => ['تعديل جولات تحصيل الأوسمة', 'badges'],
        'badges.collections.complete' => ['اعتماد جولات تحصيل الأوسمة', 'badges'],
        'badges.collections.cancel'   => ['إلغاء جولات تحصيل الأوسمة', 'badges'],

        // التقارير والإحصائيات: لا صلاحية لها — بوابتها دور مدير النظام
        // (role:admin على المسارات) فلا تظهر ولا تعمل لغيره أصلًا
    ],

    /*
    | المنح الافتراضية للأدوار الموجودة (لا تُنشأ أدوار جديدة).
    | مدير النظام يأخذ الكل: أنواع الأوسمة والمخزون المركزي إدارته وحده،
    | والتقارير والإحصائيات بوابتها دوره لا صلاحية.
    | بقية الأدوار تعمل في دورة الأوسمة اليومية داخل نطاقها: تسليمات
    | (لا يراها المعلم)، استحقاقات وتوزيعات، نواقص، وتحصيل — والتحصيل
    | وظيفة إدارية (مشرف/مدير فرع/مدير عام) فلا صلاحيات تحصيل للمعلم.
    | صلاحية stock.return وحدها تبقى لحاملي العهدة: بها يُرجع كل حامل
    | عهدته للإدارة (نموذج الإرجاع الذاتي في صفحة الاستحقاقات).
    */
    'grants' => [
        'admin' => ['*'],

        'general_manager' => [
            'badges.stock.return',
            'badges.handovers.view', 'badges.handovers.create', 'badges.handovers.cancel',
            'badges.entitlements.view', 'badges.entitlements.create', 'badges.entitlements.update', 'badges.entitlements.cancel',
            'badges.shortages.view', 'badges.shortages.resolve',
            'badges.collections.view', 'badges.collections.create', 'badges.collections.update', 'badges.collections.complete', 'badges.collections.cancel',
        ],

        'manager' => [
            'badges.stock.return',
            'badges.handovers.view', 'badges.handovers.create', 'badges.handovers.cancel',
            'badges.entitlements.view', 'badges.entitlements.create', 'badges.entitlements.update', 'badges.entitlements.cancel',
            'badges.shortages.view', 'badges.shortages.resolve',
            'badges.collections.view', 'badges.collections.create', 'badges.collections.update', 'badges.collections.complete', 'badges.collections.cancel',
        ],

        'supervisor' => [
            'badges.stock.return',
            'badges.handovers.view', 'badges.handovers.create', 'badges.handovers.cancel',
            'badges.entitlements.view', 'badges.entitlements.create', 'badges.entitlements.update', 'badges.entitlements.cancel',
            'badges.shortages.view', 'badges.shortages.resolve',
            'badges.collections.view', 'badges.collections.create', 'badges.collections.update', 'badges.collections.complete', 'badges.collections.cancel',
        ],

        'teacher' => [
            'badges.stock.return',
            'badges.entitlements.view', 'badges.entitlements.create',
            'badges.shortages.view', 'badges.shortages.resolve',
        ],
    ],
];
