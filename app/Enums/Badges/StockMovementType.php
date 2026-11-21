<?php

namespace App\Enums\Badges;

enum StockMovementType: string
{
    case InitialStock = 'initial_stock';
    case Addition = 'addition';
    case ReturnToAdmin = 'return';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::InitialStock => 'رصيد افتتاحي',
            self::Addition => 'إضافة مخزون',
            self::ReturnToAdmin => 'إرجاع إلى الإدارة',
            self::Adjustment => 'تسوية مخزون',
        };
    }

    /** إشارة الحركة في رصيد الإدارة: التسوية قد تكون موجبة أو سالبة حسب الكمية */
    public function sign(): int
    {
        return match ($this) {
            self::InitialStock, self::Addition, self::ReturnToAdmin => 1,
            self::Adjustment => 0, // تُحدد من إشارة الكمية نفسها
        };
    }
}
