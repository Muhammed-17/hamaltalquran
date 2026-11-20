<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'student_weekly_new_memorizations',
            'student_weekly_revisions',
            'student_weekly_old_memorizations',
            'student_weekly_disciplines',
            'student_weekly_tajweed_assessments',
            'student_weekly_foundation_levels',
            'student_weekly_educational_lessons',
        ];

        foreach ($tables as $table) {
            $this->addForeignSafely($table);
        }
    }

    public function down(): void
    {
        // اختياري
    }

    private function addForeignSafely(string $table): void
    {
        $constraintName = "{$table}_weekly_followup_id_foreign";

        $exists = collect(Schema::getForeignKeys($table))
            ->contains(fn($fk) => $fk['name'] === $constraintName);

        if (! $exists) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('weekly_followup_id')
                    ->references('id')->on('student_weekly_followups')
                    ->onDelete('cascade');
            });
        }
    }
};
