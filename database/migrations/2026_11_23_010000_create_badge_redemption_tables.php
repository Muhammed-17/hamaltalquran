<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * استبدال الأوسمة بلعبة: سجل استبدال لكل طالب وشهر يستهلك كل أوسمة
 * الشهر (المسلّمة في يده + المستحقة غير المسلّمة)، والمسلّمة المستهلكة
 * تعود للمخزون المركزي عبر حركات مخزون بمصدر «redemption».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('period_month', 7); // YYYY-MM — شهر الاستحقاقات المستهلكة
            $table->date('redemption_date');
            $table->string('status')->default('completed'); // completed | cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'period_month']);
        });

        Schema::create('badge_redemption_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('redemption_id')->constrained('badge_redemptions')->cascadeOnDelete();
            $table->foreignId('entitlement_id')->constrained('badge_entitlements')->restrictOnDelete();
            // صف التوزيع: الجزء المسلّم المستهلك من توزيع بعينه (لتسوية التحصيل لاحقًا)
            // صف بلا توزيع (distribution_id = null): الجزء المستحق غير المسلّم المستهلك
            $table->foreignId('distribution_id')->nullable()->constrained('badge_distributions')->restrictOnDelete();
            $table->foreignId('badge_type_id')->constrained('badge_types')->restrictOnDelete();
            $table->unsignedInteger('held_quantity')->default(0);
            $table->unsignedInteger('shortage_quantity')->default(0);

            // اسم صريح قصير: الاسم التلقائي (74 حرفًا) يتجاوز حد MySQL (64)
            $table->unique(['redemption_id', 'entitlement_id', 'distribution_id'], 'badge_redemption_items_unique');
            $table->index('entitlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_redemption_items');
        Schema::dropIfExists('badge_redemptions');
    }
};
