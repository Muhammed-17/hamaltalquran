<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->index('user_id', 'teachers_user_id_index');
            $table->index('branch_id', 'teachers_branch_id_index');
        });
    }

    public function down(): void
    {
        // عمودا user_id وbranch_id عليهما Foreign Key، والفهرسان دُول هما
        // الغطاء الوحيد ليه — لازم نضيف فهرس بديل لكل عمود قبل حذفه،
        // وإلا MySQL يرفض الحذف (error 1553) ويفشل migrate:refresh.
        // ملحوظة: الفهرس البديل لازم يفضل موجود طول ما الـ FK موجود.
        $this->addIndexIfMissing('teachers', 'user_id', 'teachers_user_id_fk_index');
        $this->addIndexIfMissing('teachers', 'branch_id', 'teachers_branch_id_fk_index');

        $this->dropIndexIfExists('teachers', 'teachers_user_id_index');
        $this->dropIndexIfExists('teachers', 'teachers_branch_id_index');
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