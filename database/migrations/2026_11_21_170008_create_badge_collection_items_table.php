<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('badge_collection_rounds');
            $table->foreignId('student_id')->constrained('students');
            // nullable: صفوف الطلاب الذين لا أوسمة لديهم للتحصيل (كمية صفر)
            $table->foreignId('distribution_id')->nullable()->constrained('badge_distributions');
            $table->foreignId('badge_type_id')->nullable()->constrained('badge_types');
            $table->unsignedInteger('distributed_quantity')->default(0);
            $table->unsignedInteger('collected_quantity')->default(0);
            $table->string('status')->default('not_collected'); // collected|partial|not_collected|missing
            $table->text('notes')->nullable();
            $table->timestamps();

            // لا تحصيل مكرر لنفس التوزيع داخل الجولة نفسها
            $table->unique(['round_id', 'distribution_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_collection_items');
    }
};
