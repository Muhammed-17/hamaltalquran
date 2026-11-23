<?php

namespace App\Enums;

/**
 * المرحلة الدراسية للطالب — القيم المخزنة هي النصوص العربية نفسها،
 * فلا ترحيل بيانات.
 */
enum EducationalStage: string
{
    case Introductory = 'تمهيدي';
    case Kindergarten = 'حضانة';
    case Primary = 'ابتدائي';
    case Middle = 'اعدادي';
    case Secondary = 'ثانوي';
    case University = 'جامعي';
    case Graduate = 'خريج';

    /** القيم كما تُخزَّن وتُرسَل في الطلبات */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
