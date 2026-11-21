<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إلغاء حقل نوع الحركة بطلب المستخدم: الرصيد الافتتاحي والإضافة شيء واحد.
     * تبقى الحركات مميزة بما يكفي دون عمود:
     * - الكمية الموجبة = دخول مخزون (افتتاحي/إضافة/دفعة طباعة)
     * - الكمية السالبة = تسوية/تصحيح (سببها إلزامي في الملاحظات)
     * - الإرجاع من معلم = source_type = teacher مع source_id
     * والكمية تُحفظ بإشارتها، فالرصيد = مجموع الكميات − التسليمات.
     *
     * ملاحظة MySQL: الفهرس المركب (badge_type_id, movement_type) يدعم مفتاح
     * FK الخاص بـ badge_type_id، فلا يُحذف قبل إنشاء فهرس بديل (خطأ 1553).
     */
    public function up(): void
    {
        Schema::table('badge_stock_movements', function (Blueprint $table) {
            $table->index('badge_type_id', 'badge_stock_movements_badge_type_id_index');
        });

        Schema::table('badge_stock_movements', function (Blueprint $table) {
            $table->dropIndex(['badge_type_id', 'movement_type']);
            $table->dropColumn('movement_type');
        });
    }

    public function down(): void
    {
        Schema::table('badge_stock_movements', function (Blueprint $table) {
            $table->string('movement_type')->default('addition');
            $table->index(['badge_type_id', 'movement_type']);
        });

        Schema::table('badge_stock_movements', function (Blueprint $table) {
            $table->dropIndex('badge_stock_movements_badge_type_id_index');
        });
    }
};
