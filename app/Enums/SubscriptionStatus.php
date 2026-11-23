<?php

namespace App\Enums;

/**
 * حالة اشتراك الطالب الشهري — القيم المخزنة هي النصوص العربية نفسها،
 * فلا ترحيل بيانات.
 */
enum SubscriptionStatus: string
{
    case Paid = 'مدفوع';
    case Unpaid = 'غير مدفوع';
    case Exempt = 'معفي';

    /** القيم كما تُخزَّن وتُرسَل في الطلبات */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** الحالات التي تُعدّ الشهر محسومًا ماليًا (مدفوع أو معفي من الدفع) */
    public static function settled(): array
    {
        return [self::Paid, self::Exempt];
    }
}
