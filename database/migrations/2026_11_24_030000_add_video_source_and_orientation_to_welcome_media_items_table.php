<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الفيديو قد يكون رابط يوتيوب أو ملفًا محليًا مرفوعًا (video_path)،
 * وله اتجاه عرض: أفقي (عادي 16:9) أو عمودي (ريلز 9:16).
 * روابط Shorts الحالية تُضبط عمودية تلقائيًا لأنها عمودية بطبيعتها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('welcome_media_items', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('video_url');
            $table->string('orientation', 12)->default('landscape')->after('video_path');
        });

        DB::table('welcome_media_items')
            ->where('type', 'video')
            ->where('video_url', 'like', '%/shorts/%')
            ->update(['orientation' => 'portrait']);
    }

    public function down(): void
    {
        Schema::table('welcome_media_items', function (Blueprint $table) {
            $table->dropColumn(['video_path', 'orientation']);
        });
    }
};
