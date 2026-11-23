<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('welcome_media_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // slider | gallery | video
            $table->string('title')->nullable();
            $table->string('image_path')->nullable();
            $table->string('video_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active', 'sort_order'], 'welcome_media_type_active_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welcome_media_items');
    }
};
