<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الصورة الواحدة قد تظهر في السلايدر والمعرض معًا دون رفعها مرتين:
 * النوع يصبح (image|video) ومكان الظهور بعلمين in_slider / in_gallery.
 * تُحوَّل البيانات الحالية: صفوف slider → صورة بعلم السلايدر،
 * وصفوف gallery → صورة بعلم المعرض، والفيديوهات كما هي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('welcome_media_items', function (Blueprint $table) {
            $table->boolean('in_slider')->default(false)->after('type');
            $table->boolean('in_gallery')->default(false)->after('in_slider');
        });

        DB::table('welcome_media_items')->where('type', 'slider')->update(['in_slider' => true, 'type' => 'image']);
        DB::table('welcome_media_items')->where('type', 'gallery')->update(['in_gallery' => true, 'type' => 'image']);
    }

    public function down(): void
    {
        // الإرجاع تقريبي: الصورة التي في السلايدر ترجع slider وإلا gallery
        DB::table('welcome_media_items')->where('type', 'image')->where('in_slider', true)->update(['type' => 'slider']);
        DB::table('welcome_media_items')->where('type', 'image')->update(['type' => 'gallery']);

        Schema::table('welcome_media_items', function (Blueprint $table) {
            $table->dropColumn(['in_slider', 'in_gallery']);
        });
    }
};
