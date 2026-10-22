<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->index('decision', 'students_decision_index');
            $table->index('school_grade', 'students_school_grade_index');
            $table->index('gender', 'students_gender_index');
            $table->index('educational_stage', 'students_educational_stage_index');
            $table->index('date_of_birth', 'students_date_of_birth_index'); // مستخدم في فلترة وترتيب العمر
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_decision_index');
            $table->dropIndex('students_school_grade_index');
            $table->dropIndex('students_gender_index');
            $table->dropIndex('students_educational_stage_index');
            $table->dropIndex('students_date_of_birth_index');
        });
    }
};