<?php

namespace App\Enums;

/**
 * مقياس التقييم في المتابعات الأسبوعية (حفظ ومراجعة وانضباط وتجويد وقاعدة
 * ودروس) — القيم المخزنة هي النصوص العربية نفسها، فلا ترحيل بيانات.
 * ملحوظة: مقياس نتائج اختبارات السور مختلف الذيل (إعادة بدل ضعيف) وله enum مستقل.
 */
enum AssessmentLevel: string
{
    case Excellent = 'ممتاز';
    case VeryGood = 'جيد جداً';
    case Good = 'جيد';
    case Acceptable = 'مقبول';
    case Weak = 'ضعيف';

    /** القيم كما تُخزَّن وتُرسَل في الطلبات */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** خريطة قيمة => تسمية لقوائم الاختيار (التسمية هي القيمة نفسها) */
    public static function options(): array
    {
        return array_combine(self::values(), self::values());
    }

    /** مستوى التقييم المقابل لنسبة مئوية (تُستخدم لتقييم الحضور في التوصيات) */
    public static function forPercentage(float $rate): self
    {
        return match (true) {
            $rate >= 90 => self::Excellent,
            $rate >= 80 => self::VeryGood,
            $rate >= 70 => self::Good,
            $rate >= 60 => self::Acceptable,
            default     => self::Weak,
        };
    }
}
