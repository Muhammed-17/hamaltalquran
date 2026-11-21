<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('circle_id')->constrained('circles');
            $table->foreignId('teacher_id')->constrained('users'); // users.id
            $table->foreignId('badge_type_id')->constrained('badge_types');
            $table->unsignedInteger('quantity');
            $table->date('entitlement_date');
            $table->string('status')->default('pending'); // pending|partial|completed|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['student_id', 'badge_type_id']);
            $table->index(['circle_id', 'status']);
            $table->index(['teacher_id', 'status']);
            $table->index('entitlement_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_entitlements');
    }
};
