<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropForeign(['center_id']);
            $table->dropColumn(['name', 'center_id']);
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->foreignId('center_id')->nullable()->constrained('centers')->nullOnDelete();
        });
    }
};
