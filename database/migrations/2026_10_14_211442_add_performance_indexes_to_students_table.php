<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->index('circle_id', 'students_circle_id_index');
            $table->index('status', 'students_status_index');
            $table->index('guardian_id', 'students_guardian_id_index');
            $table->index(['status', 'circle_id'], 'students_status_circle_id_index');
        });
    }

    public function down(): void
    {
        // عمودا circle_id وguardian_id عليهما Foreign Key، والفهرسان دُول
        // هما الغطاء الوحيد ليه — لازم نضيف فهرس بديل لكل عمود قبل حذفه،
        // وإلا MySQL يرفض الحذف (error 1553) ويفشل migrate:refresh.
        // ملحوظة: الفهرس البديل لازم يفضل موجود طول ما الـ FK موجود.
        $this->addIndexIfMissing('students', 'circle_id', 'students_circle_id_fk_index');
        $this->addIndexIfMissing('students', 'guardian_id', 'students_guardian_id_fk_index');

        $this->dropIndexIfExists('students', 'students_circle_id_index');
        $this->dropIndexIfExists('students', 'students_status_index');
        $this->dropIndexIfExists('students', 'students_guardian_id_index');
        $this->dropIndexIfExists('students', 'students_status_circle_id_index');
    }

    private function addIndexIfMissing(string $table, string $column, string $indexName): void
    {
        if (! Schema::hasIndex($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $indexName) {
                $blueprint->index($column, $indexName);
            });
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (Schema::hasIndex($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropIndex($indexName);
            });
        }
    }
};