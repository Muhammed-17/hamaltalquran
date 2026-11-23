<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ربط خصم المستحق بجولة التحصيل التي نُفّذ ضمنها (للعكس عند إلغائها) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badge_redemptions', function (Blueprint $table) {
            $table->foreignId('collection_round_id')->nullable()
                ->constrained('badge_collection_rounds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('badge_redemptions', function (Blueprint $table) {
            $table->dropForeign(['collection_round_id']);
            $table->dropColumn('collection_round_id');
        });
    }
};
