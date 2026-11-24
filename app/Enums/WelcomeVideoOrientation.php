<?php

namespace App\Enums;

enum WelcomeVideoOrientation: string
{
    case Landscape = 'landscape';
    case Portrait = 'portrait';

    public function label(): string
    {
        return match ($this) {
            self::Landscape => 'أفقي (عادي 16:9)',
            self::Portrait => 'عمودي (ريلز 9:16)',
        };
    }

    /** صنف نسبة العرض لإطار الفيديو */
    public function aspectClass(): string
    {
        return match ($this) {
            self::Landscape => 'aspect-video',
            self::Portrait => 'aspect-[9/16]',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
