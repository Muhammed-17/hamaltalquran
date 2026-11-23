<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index('teacher_id', 'subscriptions_teacher_id_index');
            $table->index('paid_at', 'subscriptions_paid_at_index');
            $table->index(['status', 'paid_at'], 'subscriptions_status_paid_at_index');
        });
    }

    public function down(): void
    {
        // عمود teacher_id عليه Foreign Key، والفهرس ده هو الغطاء الوحيد
        // ليه — لازم نضيف فهرس بديل قبل حذفه، وإلا MySQL يرفض الحذف
        // (error 1553) ويفشل migrate:refresh.
        // ملحوظة: الفهرس البديل لازم يفضل موجود طول ما الـ FK موجود.
        $this->addIndexIfMissing('subscriptions', 'teacher_id', 'subscriptions_teacher_id_fk_index');

        $this->dropIndexIfExists('subscriptions', 'subscriptions_teacher_id_index');
        $this->dropIndexIfExists('subscriptions', 'subscriptions_paid_at_index');
        $this->dropIndexIfExists('subscriptions', 'subscriptions_status_paid_at_index');
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