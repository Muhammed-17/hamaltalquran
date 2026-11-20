<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * تعبيرات SQL محمولة بين MySQL وSQLite.
 *
 * بيئة التشغيل MySQL — وتُستخدم فيها الصيغ الأصلية كما هي تمامًا.
 * بيئة الاختبارات SQLite لا تعرف دوال MySQL الخاصة (DATE_FORMAT،
 * TIMESTAMPDIFF)، فتُعاد الصيغة المكافئة لها حتى تعمل الصفحات
 * والتقارير في البيئتين بنفس النتائج.
 */
class SqlExpressions
{
    /** مفتاح الشهر بصيغة 'Y-m' لعمود تاريخ/وقت */
    public static function monthKey(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    /** العمر بالسنوات الكاملة لعمود تاريخ ميلاد */
    public static function ageYears(string $column): string
    {
        if (DB::getDriverName() === 'sqlite') {
            return "(strftime('%Y', 'now') - strftime('%Y', {$column})"
                . " - (strftime('%m-%d', 'now') < strftime('%m-%d', {$column})))";
        }

        return "TIMESTAMPDIFF(YEAR, {$column}, CURDATE())";
    }
}
