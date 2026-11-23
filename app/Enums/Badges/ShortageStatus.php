<?php

namespace App\Enums\Badges;

enum ShortageStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';
    case Redeemed = 'redeemed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'لم يُسلَّم شيء',
            self::Partial => 'سُلّم جزئيًا',
            self::Resolved => 'تم الاستكمال',
            self::Cancelled => 'ملغي',
            self::Redeemed => 'مستهلك بالاستبدال',
        };
    }
}
