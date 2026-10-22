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
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_circle_id_index');
            $table->dropIndex('students_status_index');
            $table->dropIndex('students_guardian_id_index');
            $table->dropIndex('students_status_circle_id_index');
        });
    }
};