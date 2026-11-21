<?php

namespace App\Models\Badges;

use App\Enums\Badges\StockMovementType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeStockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'badge_type_id', 'movement_type', 'quantity',
        'source_type', 'source_id', 'destination_type', 'destination_id',
        'reference_id', 'notes', 'created_by', 'created_at',
    ];

    protected $casts = [
        'movement_type' => StockMovementType::class,
        'created_at' => 'datetime',
    ];

    public function badgeType(): BelongsTo
    {
        return $this->belongsTo(BadgeType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** القيمة المؤثرة في رصيد الإدارة (التسوية تحمل إشارتها في الكمية) */
    public function signedQuantity(): int
    {
        return match ($this->movement_type) {
            StockMovementType::Adjustment => $this->quantity,
            default => abs($this->quantity),
        };
    }
}
