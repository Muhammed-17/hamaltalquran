<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذف الأعمدة غير المستخدمة من جدولَي الاستبدال:
 * - badge_redemption_items.distribution_id: كان يُكتب null دائمًا ولا يُقرأ (بقايا صفحة الاستبدال المحذوفة)
 * - badge_redemption_items.held_quantity: كان يُكتب 0 دائمًا ولا يدخل أي حساب (المحصَّل يمر عبر بنود الجولة)
 * - badge_redemptions.batch_id: كان يُكتب ولا يُقرأ (تجميع عمليات الصفحة المحذوفة)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badge_redemption_items', function (Blueprint $table) {
            // الفهرس الفريد المركّب كان هو الفهرس الداعم لمفتاح redemption_id الخارجي،
            // فلا بد من فهرس بديل عليه أولًا وإلا رفض MySQL إسقاط الفريد (خطأ 1553)
            $table->index('redemption_id', 'br_items_redemption_idx');
            $table->dropUnique('badge_redemption_items_unique');
            $table->dropForeign(['distribution_id']);
            $table->dropColumn(['distribution_id', 'held_quantity']);
        });

        Schema::table('badge_redemptions', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('badge_redemptions', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->index();
        });

        Schema::table('badge_redemption_items', function (Blueprint $table) {
            $table->foreignId('distribution_id')->nullable()->constrained('badge_distributions')->restrictOnDelete();
            $table->unsignedInteger('held_quantity')->default(0);
            $table->unique(['redemption_id', 'entitlement_id', 'distribution_id'], 'badge_redemption_items_unique');
            $table->dropIndex('br_items_redemption_idx');
        });
    }
};
