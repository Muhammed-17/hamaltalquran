<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('is_collected')->default(false)->after('status');
        });

        // Composite index يغطي استعلام "المستحقات الجديدة غير المجمّعة"
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(
                ['collected_by', 'month', 'is_collected', 'status'],
                'idx_collected_by_month_collected_status'
            );
        });
    }

    public function down(): void
    {
        // عمود collected_by عليه Foreign Key، وهو أول عمود في
        // idx_collected_by_month_collected_status، فهو بيغطيه حاليًا.
        // لازم نضيف index عادي بديل عليه قبل ما نحذف الـ composite index،
        // وإلا الـ FK هيفضل من غير غطاء (error 1553).
        $indexExists = collect(DB::select(
            "SHOW INDEX FROM subscriptions WHERE Key_name = ?",
            ['subscriptions_collected_by_index']
        ))->isNotEmpty();

        if (! $indexExists) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index('collected_by', 'subscriptions_collected_by_index');
            });
        }

        $compositeExists = collect(DB::select(
            "SHOW INDEX FROM subscriptions WHERE Key_name = ?",
            ['idx_collected_by_month_collected_status']
        ))->isNotEmpty();

        if ($compositeExists) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex('idx_collected_by_month_collected_status');
            });
        }

        // ملحوظة: مينفعش نحذف subscriptions_collected_by_index هنا،
        // لأنه بقى هو الغطاء الوحيد المتبقي للـ FK بتاع collected_by.

        if (Schema::hasColumn('subscriptions', 'is_collected')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('is_collected');
            });
        }
    }
};