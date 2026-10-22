<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('status', 'users_status_index');
            $table->index('name', 'users_name_index');
            $table->index('created_at', 'users_created_at_index'); // مستخدم في applySort
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_status_index');
            $table->dropIndex('users_name_index');
            $table->dropIndex('users_created_at_index');
        });
    }
};