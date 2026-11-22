<?php

namespace App\Enums\Badges;

enum CollectionRoundStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Cancelled = 'cancelled';


    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Completed => 'معتمدة',
            self::Cancelled => 'ملغاة',
        };
    }
}
