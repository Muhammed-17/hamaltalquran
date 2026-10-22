<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_session_plans', function (Blueprint $table) {
            $table->index('start_time', 'group_session_plans_start_time_index');
        });
    }

    public function down(): void
    {
        Schema::table('group_session_plans', function (Blueprint $table) {
            $table->dropIndex('group_session_plans_start_time_index');
        });
    }
};