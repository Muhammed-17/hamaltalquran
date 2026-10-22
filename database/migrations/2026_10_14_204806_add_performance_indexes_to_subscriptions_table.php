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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_teacher_id_index');
            $table->dropIndex('subscriptions_paid_at_index');
            $table->dropIndex('subscriptions_status_paid_at_index');
        });
    }
};