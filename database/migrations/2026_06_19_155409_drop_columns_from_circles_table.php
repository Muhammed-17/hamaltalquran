<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            // 1. حذف قيد المفتاح الأجنبي أولاً لفك الارتباط
            $table->dropForeign(['supervisor_id']);

            // 2. الآن يمكنك حذف الأعمدة الثلاثة دفعة واحدة بدون مشاكل
            $table->dropColumn(['supervisor_id', 'notes', 'max_students', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            // إعادة إنشاء الأعمدة بدون افتراض ترتيب عمود غير مؤكد الوجود
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->text('notes')->nullable();
            $table->integer('max_students')->nullable();
            $table->boolean('is_active')->default(true);

            // ⚠️ لم يتم تفعيل قيد المفتاح الأجنبي هنا لحين التأكد من الجدول المرجعي (teachers أم users)
            // $table->foreign('supervisor_id')->references('id')->on('teachers')->onDelete('set null');
        });
    }
};
