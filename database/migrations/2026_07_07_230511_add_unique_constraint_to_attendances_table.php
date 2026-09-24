<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(['student_id', 'date']);
        });
    }

    public function down(): void
    {
        // ضيف index عادي على student_id عشان الـ Foreign Key بتاعه
        // يفضل ليه index يغطيه طول الوقت (شرط InnoDB)
        // مع تأكد إنه مش موجود بالفعل (من محاولة rollback سابقة فشلت في نص الطريق)
        $indexExists = collect(DB::select(
            "SHOW INDEX FROM attendances WHERE Key_name = ?",
            ['attendances_student_id_index']
        ))->isNotEmpty();

        if (! $indexExists) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->index('student_id', 'attendances_student_id_index');
            });
        }

        // احذف الـ unique المركّب لو لسه موجود، لأن فيه index بديل
        // (attendances_student_id_index) بيغطي عمود student_id
        $uniqueExists = collect(DB::select(
            "SHOW INDEX FROM attendances WHERE Key_name = ?",
            ['attendances_student_id_date_unique']
        ))->isNotEmpty();

        if ($uniqueExists) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropUnique(['student_id', 'date']);
            });
        }

        // ملحوظة: مينفعش نحذف attendances_student_id_index هنا،
        // لأنه بقى هو الغطاء الوحيد المتبقي للـ FK بتاع student_id.
        // لازم يفضل موجود طول ما الـ FK موجود.
    }
};