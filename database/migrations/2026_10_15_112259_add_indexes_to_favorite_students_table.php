<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('favorite_students', function (Blueprint $table) {
            $table->index('circle_id', 'favorite_students_circle_id_index');
            $table->index('created_at', 'favorite_students_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('favorite_students', function (Blueprint $table) {
            //
        });
    }
};
