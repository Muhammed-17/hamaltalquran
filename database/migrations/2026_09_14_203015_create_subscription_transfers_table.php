<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_id')->nullable()->constrained('circles')->nullOnDelete();
            $table->date('month')->nullable();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('performed_by')->constrained('users')->cascadeOnDelete();
            $table->json('subscription_ids');
            $table->unsignedInteger('subscriptions_count')->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_transfers');
    }
};
