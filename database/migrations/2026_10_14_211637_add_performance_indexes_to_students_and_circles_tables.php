<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexSafely('students', ['circle_id'], 'students_circle_id_index');
        $this->addIndexSafely('students', ['status'], 'students_status_index');
        $this->addIndexSafely('students', ['guardian_id'], 'students_guardian_id_index');
        $this->addIndexSafely('students', ['center_id'], 'students_center_id_index');
        $this->addIndexSafely('students', ['supervisor_id'], 'students_supervisor_id_index');
        $this->addIndexSafely('students', ['status', 'circle_id'], 'students_status_circle_id_index');
        $this->addIndexSafely('circles', ['branch_id'], 'circles_branch_id_index');
    }

    public function down(): void
    {
        // اختياري
    }

    private function addIndexSafely(string $table, array $columns, string $indexName): void
    {
        $exists = DB::select("
            SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?
        ", [$table, $indexName]);

        if (empty($exists)) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
                $blueprint->index($columns, $indexName);
            });
        }
    }
};