<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_shortages', function (Blueprint $table) {
            $table->id();
            // عجز واحد فقط لكل استحقاق
            $table->foreignId('entitlement_id')->unique()->constrained('badge_entitlements');
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('teacher_id')->constrained('users'); // users.id
            $table->foreignId('badge_type_id')->constrained('badge_types');
            $table->unsignedInteger('required_quantity');
            $table->unsignedInteger('delivered_quantity')->default(0);
            $table->unsignedInteger('shortage_quantity');
            $table->string('status')->default('pending'); // pending|partial|resolved|cancelled
            $table->timestamps();

            $table->index(['teacher_id', 'status']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_shortages');
    }
};
