<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سلسلة العهدة الهرمية: التسليم قد يكون من المخزون المركزي (مدير النظام)
     * أو من عهدة مستخدم أعلى رتبة (مدير عام/مدير فرع/مشرف).
     * source_user_id = null تعني «من الإدارة المركزية»، وأي قيمة تعني
     * «من عهدة هذا المستخدم» فتُخصم من رصيده. الصفوف القديمة كلها مركزية.
     */
    public function up(): void
    {
        Schema::table('badge_teacher_handovers', function (Blueprint $table) {
            $table->foreignId('source_user_id')->nullable()->after('teacher_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('badge_teacher_handovers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_user_id');
        });
    }
};
