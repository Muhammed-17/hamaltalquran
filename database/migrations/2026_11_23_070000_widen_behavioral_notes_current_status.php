<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عمود current_status كان VARCHAR(50) بينما النموذج يقدّمه كنص حر
 * (textarea بحد 2000 حرف) — أي نص أطول من 50 حرفًا يفشل على MySQL
 * بخطأ Data too long. يُوسَّع إلى TEXT مثل behavior و action_taken.
 * الفهرسان على العمود (idx_behavior_status و idx_behavior_status_followup
 * المركّب) يُحذفان أولًا: لا يمكن فهرسة عمود TEXT دون طول بادئة
 * (خطأ MySQL 1071)، ولا توجد أي استعلامات تُرشّح بهذا العمود أصلًا
 * (الترشيح بالحالة يتم عبر عمود status).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('behavioral_notes', function (Blueprint $table) {
            $table->dropIndex('idx_behavior_status');
            $table->dropIndex('idx_behavior_status_followup');
        });

        Schema::table('behavioral_notes', function (Blueprint $table) {
            $table->text('current_status')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('behavioral_notes', function (Blueprint $table) {
            $table->string('current_status', 50)->nullable()->change();
        });

        Schema::table('behavioral_notes', function (Blueprint $table) {
            $table->index('current_status', 'idx_behavior_status');
            $table->index(['current_status', 'follow_up_at'], 'idx_behavior_status_followup');
        });
    }
};
