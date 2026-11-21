<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إصلاح نظام التوصيات: منع تكرار صفوف التوصيات لنفس المتابعة الأسبوعية.
 *
 * الخلفية: RecommendationService يحفظ التوصيات عبر upsert بمفتاح
 * weekly_followup_id، لكن الجدول لم يكن عليه أي قيد فريد على هذا العمود
 * (فهرس عادي فقط). على MySQL كان كل توليد يُدخل صفًا جديدًا بدل تحديث
 * القديم — فتتراكم نسخ مكررة وتعرض الصفحة أول نسخة (قديمة) دائمًا،
 * وعلى SQLite كان التوليد ينهار بخطأ ON CONFLICT.
 *
 * هذه الـ migration:
 * 1) تحذف الصفوف المكررة الحالية مع إبقاء صف واحد لكل متابعة:
 *    تُفضَّل الصفوف التي فيها مراجعة مشرف مكتوبة، ثم الأحدث (أكبر id).
 * 2) تضيف قيدًا فريدًا على weekly_followup_id فيعمل الـ upsert كما صُمم.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) إزالة التكرارات الحالية إن وجدت
        $duplicateFollowupIds = DB::table('recommendations')
            ->select('weekly_followup_id')
            ->groupBy('weekly_followup_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('weekly_followup_id');

        foreach ($duplicateFollowupIds as $followupId) {
            $keepId = DB::table('recommendations')
                ->where('weekly_followup_id', $followupId)
                ->orderByRaw('CASE WHEN supervisor_recommendation IS NOT NULL THEN 0 ELSE 1 END')
                ->orderByDesc('id')
                ->value('id');

            DB::table('recommendations')
                ->where('weekly_followup_id', $followupId)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        // 2) القيد الفريد (يُضاف أولًا حتى يعتمد عليه مفتاح الربط، ثم يُحذف الفهرس العادي)
        Schema::table('recommendations', function (Blueprint $table) {
            $table->unique('weekly_followup_id', 'uniq_recommendations_followup');
        });

        Schema::table('recommendations', function (Blueprint $table) {
            $table->dropIndex('idx_recommendations_followup');
        });
    }

    public function down(): void
    {
        Schema::table('recommendations', function (Blueprint $table) {
            $table->index('weekly_followup_id', 'idx_recommendations_followup');
        });

        Schema::table('recommendations', function (Blueprint $table) {
            $table->dropUnique('uniq_recommendations_followup');
        });
    }
};
