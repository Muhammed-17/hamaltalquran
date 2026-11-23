<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_transfers', function (Blueprint $table) {
            $table->index('circle_id', 'subscription_transfers_circle_id_index');
            $table->index('month', 'subscription_transfers_month_index');
            $table->index('from_user_id', 'subscription_transfers_from_user_id_index');
            $table->index('to_user_id', 'subscription_transfers_to_user_id_index');
            $table->index('performed_by', 'subscription_transfers_performed_by_index');
            $table->index('created_at', 'subscription_transfers_created_at_index');
        });
    }

    public function down(): void
    {
        // الأعمدة circle_id وfrom_user_id وto_user_id وperformed_by عليها
        // Foreign Key، والفهارس دي هي الغطاء الوحيد ليها — لازم نضيف
        // فهرس بديل لكل عمود قبل حذفه، وإلا MySQL يرفض الحذف
        // (error 1553) ويفشل migrate:refresh.
        // ملحوظة: الفهرس البديل لازم يفضل موجود طول ما الـ FK موجود.
        $this->addIndexIfMissing('subscription_transfers', 'circle_id', 'subscription_transfers_circle_id_fk_index');
        $this->addIndexIfMissing('subscription_transfers', 'from_user_id', 'subscription_transfers_from_user_id_fk_index');
        $this->addIndexIfMissing('subscription_transfers', 'to_user_id', 'subscription_transfers_to_user_id_fk_index');
        $this->addIndexIfMissing('subscription_transfers', 'performed_by', 'subscription_transfers_performed_by_fk_index');

        $this->dropIndexIfExists('subscription_transfers', 'subscription_transfers_circle_id_index');
        $this->dropIndexIfExists('subscription_transfers', 'subscription_transfers_month_index');
        $this->dropIndexIfExists('subscription_transfers', 'subscription_transfers_from_user_id_index');
        $this->dropIndexIfExists('subscription_transfers', 'subscription_transfers_to_user_id_index');
        $this->dropIndexIfExists('subscription_transfers', 'subscription_transfers_performed_by_index');
        $this->dropIndexIfExists('subscription_transfers', 'subscription_transfers_created_at_index');
    }

    private function addIndexIfMissing(string $table, string $column, string $indexName): void
    {
        if (! Schema::hasIndex($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $indexName) {
                $blueprint->index($column, $indexName);
            });
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (Schema::hasIndex($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropIndex($indexName);
            });
        }
    }
};
