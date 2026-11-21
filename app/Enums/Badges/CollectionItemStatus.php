<?php

namespace App\Enums\Badges;

enum CollectionItemStatus: string
{
    case Collected = 'collected';
    case Partial = 'partial';
    case NotCollected = 'not_collected';
    case Missing = 'missing';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Collected => 'تم التحصيل',
            self::Partial => 'تحصيل جزئي',
            self::NotCollected => 'لم يُحصَّل',
            self::Missing => 'مفقود',
            // تالف: رجع من الطالب فعليًا لكن غير صالح للاستخدام —
            // يُسوّى من حساب الطالب ولا يعود لعهدة المعلم
            self::Damaged => 'تالف',
        };
    }

    /** الحالة المستنتجة من الكميات (الفقدان يُسجل يدويًا فقط) */
    public static function fromQuantities(int $distributed, int $collected): self
    {
        if ($distributed > 0 && $collected >= $distributed) {
            return self::Collected;
        }
        if ($collected > 0) {
            return self::Partial;
        }
        return self::NotCollected;
    }
}
