<?php

namespace App\Models\Badges;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeStockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'badge_type_id', 'quantity',
        'source_type', 'source_id', 'destination_type', 'destination_id',
        'reference_id', 'notes', 'created_by', 'created_at',
    ];

    protected $casts = [
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

    /** القيمة المؤثرة في رصيد الإدارة — الكمية محفوظة بإشارتها أصلًا */
    public function signedQuantity(): int
    {
        return $this->quantity;
    }

    /** وصف الحركة المستنتج (لا يوجد عمود نوع حركة) */
    public function description(): string
    {
        if ($this->source_type === 'teacher') {
            return 'إرجاع من معلم';
        }

        return $this->quantity < 0 ? 'تسوية / تصحيح' : 'إضافة مخزون';
    }

    public function isReturn(): bool
    {
        return $this->source_type === 'teacher';
    }
}
