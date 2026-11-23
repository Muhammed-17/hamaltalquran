<?php

namespace App\Enums;

/** قرار الإدارة بشأن الطالب — القيم المخزنة هي النصوص العربية نفسها. */
enum StudentDecision: string
{
    case UnderReview = 'تحت الاختبار';
    case Accepted = 'مقبول';
    case Rejected = 'مرفوض';

    /** القيم كما تُخزَّن وتُرسَل في الطلبات */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
