<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::table('teachers', function (Blueprint $table) {
            $table->foreignId('center_id')->nullable()
                ->constrained('centers')->nullOnDelete();
        });

        Schema::table('circles', function (Blueprint $table) {
            $table->foreignId('center_id')->nullable()
                ->constrained('centers')->nullOnDelete();
        });
    }


    public function down(): void
    {

        Schema::table('teachers', function (Blueprint $table) {
            if (Schema::hasColumn('teachers', 'center_id')) {
                $this->dropForeignIfExists('teachers', 'center_id');
                $table->dropColumn('center_id');
            }
        });

        Schema::table('circles', function (Blueprint $table) {
            if (Schema::hasColumn('circles', 'center_id')) {
                $this->dropForeignIfExists('circles', 'center_id');
                $table->dropColumn('center_id');
            }
        });
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        $constraintName = "{$table}_{$column}_foreign";

        $exists = collect(\DB::select("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ", [$table, $column]))->isNotEmpty();

        if ($exists) {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropForeign([$column]);
            });
        }
    }
};
