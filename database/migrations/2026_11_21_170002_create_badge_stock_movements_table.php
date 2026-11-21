<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // دفتر حركة المخزون المركزي — ليس جدول رصيد.
        // التسليم للمعلمين لا يُسجل هنا (مصدره badge_teacher_handovers) منعًا للازدواج.
        Schema::create('badge_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('badge_type_id')->constrained('badge_types');
            $table->string('movement_type'); // initial_stock|addition|return|adjustment
            $table->integer('quantity'); // موجب دائمًا إلا التسوية فقد تكون سالبة
            $table->string('source_type')->nullable(); // admin|teacher
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('destination_type')->nullable(); // admin|teacher
            $table->unsignedBigInteger('destination_id')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->index(['badge_type_id', 'movement_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_stock_movements');
    }
};
