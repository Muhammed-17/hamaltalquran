<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_collection_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_id')->constrained('circles');
            $table->foreignId('teacher_id')->constrained('users'); // users.id
            $table->date('collection_date');
            $table->string('status')->default('draft'); // draft|completed|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['circle_id', 'status']);
            $table->index('collection_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_collection_rounds');
    }
};
