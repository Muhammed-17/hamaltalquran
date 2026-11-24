<?php

namespace App\Models;

use App\Enums\WelcomeMediaType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class WelcomeMediaItem extends Model
{
    protected $fillable = [
        'type',
        'in_slider',
        'in_gallery',
        'title',
        'image_path',
        'video_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'type' => WelcomeMediaType::class,
        'in_slider' => 'boolean',
        'in_gallery' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // حذف ملف الصورة من التخزين عند حذف العنصر
        static::deleting(function (WelcomeMediaItem $item) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, WelcomeMediaType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /** صور تظهر في السلايدر */
    public function scopeForSlider(Builder $query): Builder
    {
        return $query->where('type', WelcomeMediaType::Image->value)->where('in_slider', true);
    }

    /** صور تظهر في المعرض */
    public function scopeForGallery(Builder $query): Builder
    {
        return $query->where('type', WelcomeMediaType::Image->value)->where('in_gallery', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /** معرّف فيديو يوتيوب من أي صيغة رابط شائعة، أو null إن لم يكن رابط يوتيوب صالحًا */
    public static function youtubeId(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $patterns = [
            '/youtube\.com\/watch\?[^#]*\bv=([A-Za-z0-9_-]{6,})/',
            '/youtu\.be\/([A-Za-z0-9_-]{6,})/',
            '/youtube\.com\/(?:embed|shorts|live)\/([A-Za-z0-9_-]{6,})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    public function youtubeIdValue(): ?string
    {
        return self::youtubeId($this->video_url);
    }

    public function youtubeThumbnailUrl(): ?string
    {
        $id = $this->youtubeIdValue();

        return $id ? "https://i.ytimg.com/vi/{$id}/hqdefault.jpg" : null;
    }

    public function youtubeEmbedUrl(): ?string
    {
        $id = $this->youtubeIdValue();

        return $id ? "https://www.youtube-nocookie.com/embed/{$id}?rel=0" : null;
    }
}
