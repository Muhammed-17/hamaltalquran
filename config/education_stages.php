<?php

/**
 * جدول الأعمار المتوقعة للمراحل والصفوف الدراسية.
 *
 * يُستخدم في خدمة EducationStageExclusionService لمطابقة سن الطالب
 * بمرحلته/صفه: صفحة «مراجعة مستثناة» تعرض من لا يطابق الجدول،
 * وأمر المزامنة students:sync-education-stage يستخدمه عند التشغيل اليدوي.
 *
 * المفتاح هو السن، وكل صف هو (المرحلة، الصف) المتوقع ابتداءً من هذا السن.
 * عدّل الأعمار هنا وستنعكس في المطابقة فورًا.
 */
return [

    'thresholds' => [
        4  => ['educational_stage' => 'حضانة',  'school_grade' => 'لا يوجد', 'education_type' => null],
        5  => ['educational_stage' => 'حضانة',  'school_grade' => 'لا يوجد', 'education_type' => null],
        6  => ['educational_stage' => 'ابتدائي', 'school_grade' => 'الأول',   'education_type' => null],
        7  => ['educational_stage' => 'ابتدائي', 'school_grade' => 'الثاني',  'education_type' => null],
        8  => ['educational_stage' => 'ابتدائي', 'school_grade' => 'الثالث',  'education_type' => null],
        9  => ['educational_stage' => 'ابتدائي', 'school_grade' => 'الرابع',  'education_type' => null],
        10 => ['educational_stage' => 'ابتدائي', 'school_grade' => 'الخامس',  'education_type' => null],
        11 => ['educational_stage' => 'ابتدائي', 'school_grade' => 'السادس',  'education_type' => null],
        12 => ['educational_stage' => 'اعدادي',  'school_grade' => 'الأول',   'education_type' => null],
        13 => ['educational_stage' => 'اعدادي',  'school_grade' => 'الثاني',  'education_type' => null],
        14 => ['educational_stage' => 'اعدادي',  'school_grade' => 'الثالث',  'education_type' => null],
        15 => ['educational_stage' => 'ثانوي',   'school_grade' => 'الأول',   'education_type' => null],
        16 => ['educational_stage' => 'ثانوي',   'school_grade' => 'الثاني',  'education_type' => null],
        17 => ['educational_stage' => 'ثانوي',   'school_grade' => 'الثالث',  'education_type' => null],
        18 => ['educational_stage' => 'جامعي',   'school_grade' => 'الأول',   'education_type' => null],
        19 => ['educational_stage' => 'جامعي',   'school_grade' => 'الثاني',  'education_type' => null],
        20 => ['educational_stage' => 'جامعي',   'school_grade' => 'الثالث',  'education_type' => null],
        21 => ['educational_stage' => 'جامعي',   'school_grade' => 'الرابع',  'education_type' => null],
    ],

    // صفوف مستثناة من مطابقة السن (تظهر في صفحة المراجعة بسبب الاستثناء نفسه)
    'excluded_grades' => ['دراسات عليا'],

];
