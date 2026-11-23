<?php

namespace App\Enums\Badges;

enum RedemptionStatus: string
{
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغي',
        };
    }
}
