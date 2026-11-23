<?php

namespace App\Enums;

/**
 * مستويات نتيجة اختبار السورة — مثل مقياس المتابعة الأسبوعية في أوله،
 * لكن ذيله «إعادة» بدل «ضعيف»: Enum مستقل ولا يُدمج مع AssessmentLevel.
 * القيم المخزنة هي النصوص العربية نفسها، فلا ترحيل بيانات.
 */
enum SurahTestLevel: string
{
    case Excellent = 'ممتاز';
    case VeryGood = 'جيد جداً';
    case Good = 'جيد';
    case Acceptable = 'مقبول';
    case Repeat = 'إعادة';

    /** القيم كما تُخزَّن وتُرسَل في الطلبات */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** أصناف ألوان الشارة في صفحات العرض */
    public function colorClasses(): string
    {
        return match ($this) {
            self::Excellent  => 'bg-emerald-50 text-emerald-700',
            self::VeryGood   => 'bg-blue-50 text-blue-700',
            self::Good       => 'bg-amber-50 text-amber-700',
            self::Acceptable => 'bg-orange-50 text-orange-700',
            self::Repeat     => 'bg-red-50 text-red-700',
        };
    }
}
