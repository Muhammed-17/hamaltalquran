<?php

namespace App\Enums;

/** الجنس — القيم المخزنة هي النصوص العربية نفسها. */
enum Gender: string
{
    case Male = 'ذكر';
    case Female = 'أنثى';

    /** القيم كما تُخزَّن وتُرسَل في الطلبات */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
