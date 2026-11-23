<?php

namespace App\Enums;

enum WelcomeMediaType: string
{
    case Slider = 'slider';
    case Gallery = 'gallery';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Slider => 'السلايدر',
            self::Gallery => 'معرض الصور',
            self::Video => 'الفيديوهات',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
