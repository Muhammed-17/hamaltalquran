<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * معرّف الدفعة: التسليمات المسجلة بعملية واحدة (عدة أنواع لمستلم واحد)
     * تشترك في نفس المعرّف فتظهر مجموعة واحدة في القائمة.
     * الصفوف القديمة بلا معرّف وتظهر كلٌّ منها مجموعة مستقلة.
     */
    public function up(): void
    {
        Schema::table('badge_teacher_handovers', function (Blueprint $table) {
            $table->string('batch_id', 36)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('badge_teacher_handovers', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
