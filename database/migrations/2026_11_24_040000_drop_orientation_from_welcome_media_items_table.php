<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إلغاء مفهوم اتجاه العرض نهائيًا: يُحذف عمود orientation من جدول
 * عناصر وسائط الصفحة الرئيسية (الصور والفيديوهات تُعرض كلها بمعالجة موحّدة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('welcome_media_items', function (Blueprint $table) {
            $table->dropColumn('orientation');
        });
    }

    public function down(): void
    {
        Schema::table('welcome_media_items', function (Blueprint $table) {
            $table->string('orientation', 12)->default('landscape');
        });
    }
};
