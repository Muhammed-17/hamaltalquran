<?php

namespace App\Enums;

/**
 * حالة الطالب — القيم المخزنة في قاعدة البيانات هي النصوص العربية نفسها،
 * فلا ترحيل بيانات: الـenum يلفّ الكود حول القيم الحالية ويمنع الانحراف.
 */
enum StudentStatus: string
{
    case Enrolled = 'مقيد';
    case Stopped = 'متوقف';
    case Traveling = 'مسافر';

    /** القيم كما تُخزَّن وتُرسَل في الطلبات */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** هل الطالب نشط دراسيًا (يُحتسب في الحلقات والحضور والمتابعات)؟ */
    public function isActive(): bool
    {
        return $this === self::Enrolled;
    }
}
