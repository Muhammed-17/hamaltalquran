<?php

namespace App\Enums\Badges;

enum EntitlementStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Partial => 'تم جزئيًا',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغي',
        };
    }
}
