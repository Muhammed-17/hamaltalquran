<?php

namespace App\Enums;

/**
 * الصف الدراسي للطالب — القيم المخزنة هي النصوص العربية نفسها،
 * فلا ترحيل بيانات. promoted() تحمل خريطة الترقية لصف العام الجديد
 * التي كانت مبعثرة في نموذج الطالب.
 */
enum SchoolGrade: string
{
    case None = 'لا يوجد';
    case First = 'الأول';
    case Second = 'الثاني';
    case Third = 'الثالث';
    case Fourth = 'الرابع';
    case Fifth = 'الخامس';
    case Sixth = 'السادس';
    case PostGraduate = 'دراسات عليا';

    /** القيم كما تُخزَّن وتُرسَل في الطلبات */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** الصف المقترح للعام الجديد، أو null إن لم تكن له ترقية (لا يوجد/دراسات عليا) */
    public function promoted(): ?self
    {
        return match ($this) {
            self::First  => self::Second,
            self::Second => self::Third,
            self::Third  => self::Fourth,
            self::Fourth => self::Fifth,
            self::Fifth  => self::Sixth,
            self::Sixth  => self::None,
            default      => null,
        };
    }
}
