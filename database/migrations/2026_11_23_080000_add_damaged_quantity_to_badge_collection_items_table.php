<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * كمية التالف/المفقود داخل كل بند تحصيل على حدة.
     *
     * بند التحصيل الواحد قد يكون مختلطًا (جزء سليم وجزء تالف) منذ أن أصبحت
     * التسوية طالبًا طالبًا بمبالغ إجمالية لكل نوع: الكمية المحصلة تُحفظ
     * مجمعة وحالة البند «تالف» إن وُجد أي جزء تالف — فكان الجزء السليم
     * يضيع من عهدة المسؤول ومن كل العروض. العمود الجديد يحفظ الجزء التالف
     * صراحة، والسليم = المحصَّل − التالف في كل القراءات.
     */
    public function up(): void
    {
        Schema::table('badge_collection_items', function (Blueprint $table) {
            $table->unsignedInteger('damaged_quantity')->default(0)->after('collected_quantity');
        });

        // البيانات القديمة: القراءة السائدة كانت تعتبر محصَّل أي بند تالف/مفقود
        // تالفًا بالكامل — نُبقي أرقامها كما كانت تمامًا، والدقة الكاملة للبنود الجديدة.
        DB::table('badge_collection_items')
            ->whereIn('status', ['damaged', 'missing'])
            ->update(['damaged_quantity' => DB::raw('collected_quantity')]);
    }

    public function down(): void
    {
        Schema::table('badge_collection_items', function (Blueprint $table) {
            $table->dropColumn('damaged_quantity');
        });
    }
};
