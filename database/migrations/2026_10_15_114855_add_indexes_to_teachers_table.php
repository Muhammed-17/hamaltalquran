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
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropIndex('teachers_user_id_index');
            $table->dropIndex('teachers_branch_id_index');
        });
    }
};