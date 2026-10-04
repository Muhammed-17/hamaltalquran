<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * قيود موجودة في MySQL ولم تُنشأ في PostgreSQL بعد النقل بـ pgloader
     * (لأن جداول الآباء لم تكن قد حُمّلت وقت إنشائها).
     * [اسم القيد، الجدول، العمود، الجدول المرجعي، قاعدة الحذف]
     */
    private array $foreignKeys = [
        ['collection_rounds_circle_id_foreign', 'collection_rounds', 'circle_id', 'circles', 'CASCADE'],
        ['collection_round_items_subscription_id_foreign', 'collection_round_items', 'subscription_id', 'subscriptions', 'CASCADE'],
        ['student_construction_details_circle_id_foreign', 'student_construction_details', 'circle_id', 'circles', 'SET NULL'],
        ['student_construction_details_student_id_foreign', 'student_construction_details', 'student_id', 'students', 'SET NULL'],
        ['student_ibda_details_student_id_foreign', 'student_ibda_details', 'student_id', 'students', 'CASCADE'],
        ['student_itqan_details_student_id_foreign', 'student_itqan_details', 'student_id', 'students', 'CASCADE'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->foreignKeys as [$name, $table, $column, $refTable, $onDelete]) {
            $exists = DB::selectOne(
                "SELECT 1 FROM pg_constraint
                 WHERE conname = ? AND conrelid = ?::regclass AND contype = 'f'",
                [$name, "public.{$table}"]
            );

            if ($exists) {
                continue;
            }

            DB::statement(
                "ALTER TABLE {$table}
                 ADD CONSTRAINT \"{$name}\"
                 FOREIGN KEY ({$column}) REFERENCES {$refTable} (id)
                 ON DELETE {$onDelete}"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->foreignKeys as [$name, $table]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS \"{$name}\"");
        }
    }
};
