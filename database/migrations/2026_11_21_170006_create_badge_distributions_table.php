<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitlement_id')->constrained('badge_entitlements');
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('teacher_id')->constrained('users'); // users.id
            $table->foreignId('badge_type_id')->constrained('badge_types');
            $table->unsignedInteger('quantity');
            $table->date('distribution_date');
            $table->foreignId('shortage_id')->nullable()->constrained('badge_shortages');
            $table->text('notes')->nullable();
            $table->string('status')->default('active'); // active|cancelled
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['student_id', 'badge_type_id']);
            $table->index(['teacher_id', 'status']);
            $table->index('entitlement_id');
            $table->index('distribution_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_distributions');
    }
};
