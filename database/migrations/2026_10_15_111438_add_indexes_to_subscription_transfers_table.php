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
        Schema::table('subscription_transfers', function (Blueprint $table) {
            $table->dropIndex('subscription_transfers_circle_id_index');
            $table->dropIndex('subscription_transfers_month_index');
            $table->dropIndex('subscription_transfers_from_user_id_index');
            $table->dropIndex('subscription_transfers_to_user_id_index');
            $table->dropIndex('subscription_transfers_performed_by_index');
            $table->dropIndex('subscription_transfers_created_at_index');
        });
    }
};
