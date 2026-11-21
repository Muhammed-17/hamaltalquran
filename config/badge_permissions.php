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

        // استحقاقات الطلاب
        'badges.entitlements.view'    => ['عرض استحقاقات الأوسمة', 'badges'],
        'badges.entitlements.create'  => ['تسجيل استحقاقات الأوسمة', 'badges'],
        'badges.entitlements.update'  => ['تعديل استحقاقات الأوسمة', 'badges'],
        'badges.entitlements.cancel'  => ['إلغاء استحقاقات الأوسمة', 'badges'],

        // توزيع الأوسمة
        'badges.distributions.view'   => ['عرض توزيعات الأوسمة', 'badges'],
        'badges.distributions.create' => ['توزيع الأوسمة على الطلاب', 'badges'],
        'badges.distributions.cancel' => ['إلغاء توزيعات الأوسمة', 'badges'],

        // الأوسمة الناقصة
        'badges.shortages.view'       => ['عرض الأوسمة الناقصة', 'badges'],
        'badges.shortages.resolve'    => ['استكمال الأوسمة الناقصة', 'badges'],

        // تحصيل الأوسمة
        'badges.collections.view'     => ['عرض جولات تحصيل الأوسمة', 'badges'],
        'badges.collections.create'   => ['إنشاء جولات تحصيل الأوسمة', 'badges'],
        'badges.collections.update'   => ['تعديل جولات تحصيل الأوسمة', 'badges'],
        'badges.collections.complete' => ['اعتماد جولات تحصيل الأوسمة', 'badges'],
        'badges.collections.cancel'   => ['إلغاء جولات تحصيل الأوسمة', 'badges'],

        // التقارير وسجل العمليات
        'badges.reports.view'         => ['عرض تقارير الأوسمة', 'badges'],
        'badges.logs.view'            => ['عرض سجل عمليات الأوسمة', 'badges'],
    ],

    /*
    | المنح الافتراضية للأدوار الموجودة (لا تُنشأ أدوار جديدة).
    | الأدمن يأخذ الكل. المدير العام يدير النظام مركزيًا.
    | مدير المركز يدير دورة الأوسمة داخل نطاقه.
    | المعلم يسجل الاستحقاقات ويوزع ويحصّل داخل حلقاته فقط.
    */
    'grants' => [
        'admin' => ['*'],

        'general_manager' => [
            'badges.types.view', 'badges.types.create', 'badges.types.update',
            'badges.stock.view', 'badges.stock.add', 'badges.stock.adjust', 'badges.stock.return', 'badges.stock.history',
            'badges.handovers.view', 'badges.handovers.create', 'badges.handovers.cancel',
            'badges.entitlements.view', 'badges.entitlements.create', 'badges.entitlements.update', 'badges.entitlements.cancel',
            'badges.distributions.view', 'badges.distributions.create', 'badges.distributions.cancel',
            'badges.shortages.view', 'badges.shortages.resolve',
            'badges.collections.view', 'badges.collections.create', 'badges.collections.update', 'badges.collections.complete', 'badges.collections.cancel',
            'badges.reports.view', 'badges.logs.view',
        ],

        'manager' => [
            'badges.types.view',
            'badges.stock.view', 'badges.stock.history', 'badges.stock.return',
            'badges.handovers.view', 'badges.handovers.create', 'badges.handovers.cancel',
            'badges.entitlements.view', 'badges.entitlements.create', 'badges.entitlements.update', 'badges.entitlements.cancel',
            'badges.distributions.view', 'badges.distributions.create', 'badges.distributions.cancel',
            'badges.shortages.view', 'badges.shortages.resolve',
            'badges.collections.view', 'badges.collections.create', 'badges.collections.update', 'badges.collections.complete', 'badges.collections.cancel',
            'badges.reports.view', 'badges.logs.view',
        ],

        'supervisor' => [
            'badges.types.view',
            'badges.stock.view', 'badges.stock.return',
            'badges.handovers.view', 'badges.handovers.create', 'badges.handovers.cancel',
            'badges.entitlements.view', 'badges.entitlements.create', 'badges.entitlements.update', 'badges.entitlements.cancel',
            'badges.distributions.view', 'badges.distributions.create', 'badges.distributions.cancel',
            'badges.shortages.view', 'badges.shortages.resolve',
            'badges.collections.view', 'badges.collections.create', 'badges.collections.update', 'badges.collections.complete', 'badges.collections.cancel',
            'badges.reports.view',
        ],

        'teacher' => [
            'badges.types.view',
            'badges.stock.return',
            'badges.handovers.view',
            'badges.entitlements.view', 'badges.entitlements.create',
            'badges.distributions.view', 'badges.distributions.create',
            'badges.shortages.view', 'badges.shortages.resolve',
            'badges.collections.view', 'badges.collections.create', 'badges.collections.update', 'badges.collections.complete',
        ],
    ],
];
