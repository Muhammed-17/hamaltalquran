<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('circles', 'center_id')) {
            // ✅ MySQL/MariaDB فقط: احذف الـ FK القديم (بأي اسم كان فعليًا) قبل حذف العمود.
            // في PostgreSQL حذف العمود يحذف الـ FK والـ indexes المرتبطة به تلقائيًا.
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                $fk = DB::selectOne("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'circles'
                    AND COLUMN_NAME = 'center_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");

                if ($fk) {
                    DB::statement("ALTER TABLE circles DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                }
            }

            Schema::table('circles', function (Blueprint $table) {
                $table->dropColumn('center_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('circles', 'center_id')) {
            Schema::table('circles', function (Blueprint $table) {
                $table->foreignId('center_id')->nullable()->after('branch_id')
                    ->constrained('centers')->nullOnDelete();
            });

            // استرجاع القيمة من الفرع الحالي بتاع كل حلقة.
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                // ✅ MySQL/MariaDB
                DB::statement("
                    UPDATE circles c
                    JOIN branches b ON b.id = c.branch_id
                    SET c.center_id = b.center_id
                    WHERE c.branch_id IS NOT NULL
                ");
            } else {
                // ✅ PostgreSQL (و SQLite 3.33+): UPDATE ... FROM
                DB::statement("
                    UPDATE circles
                    SET center_id = b.center_id
                    FROM branches b
                    WHERE b.id = circles.branch_id
                    AND circles.branch_id IS NOT NULL
                ");
            }
        }
    }
};
