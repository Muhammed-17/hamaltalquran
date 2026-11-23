<?php

namespace App\Enums;

/**
 * حالة حضور الطالب — القيم المخزنة إنجليزية كما هي، فلا ترحيل بيانات.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';

    /** القيم كما تُخزَّن وتُرسَل في الطلبات */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** التسمية العربية المعروضة */
    public function label(): string
    {
        return match ($this) {
            self::Present => 'حاضر',
            self::Absent  => 'غائب',
            self::Late    => 'متأخر',
            self::Excused => 'بعذر',
        };
    }
}
