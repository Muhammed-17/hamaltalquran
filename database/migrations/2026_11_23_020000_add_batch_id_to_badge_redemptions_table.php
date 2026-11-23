<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** رقم عملية (batch) لكل استبدال — كتجميع الاستحقاقات وتسليمات العهدة */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badge_redemptions', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('badge_redemptions', function (Blueprint $table) {
            $table->dropColumn('batch_id');
        });
    }
};
