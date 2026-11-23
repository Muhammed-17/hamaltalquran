<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * حذف عمود card (عدد الأوسمة اليدوي القديم) من جدول الحضور —
     * حلّ نظام الأوسمة محلّه بالكامل.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('card');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedInteger('card')->default(0);
        });
    }
};
