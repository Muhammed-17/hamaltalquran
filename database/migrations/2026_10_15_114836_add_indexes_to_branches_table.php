<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->index('established_at', 'branches_established_at_index');
            $table->index('created_at', 'branches_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex('branches_established_at_index');
            $table->dropIndex('branches_created_at_index');
        });
    }
};