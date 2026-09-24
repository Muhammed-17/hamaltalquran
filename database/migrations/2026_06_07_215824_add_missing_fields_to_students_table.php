<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('decision')->nullable();
            $table->decimal('subscription_fees', 8, 2)->nullable();
            $table->string('received_tools')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'decision',
                'subscription_fees',
                'received_tools',
            ]);
        });
    }
};
