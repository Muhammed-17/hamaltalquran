<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // teacher_id = users.id (نفس اصطلاح جدول الاشتراكات في المشروع)
        Schema::create('badge_teacher_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users');
            $table->foreignId('badge_type_id')->constrained('badge_types');
            $table->unsignedInteger('quantity');
            $table->date('handover_date');
            $table->text('notes')->nullable();
            $table->string('status')->default('active'); // active|cancelled
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['teacher_id', 'badge_type_id']);
            $table->index('handover_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_teacher_handovers');
    }
};
