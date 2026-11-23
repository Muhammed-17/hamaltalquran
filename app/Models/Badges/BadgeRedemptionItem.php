<?php

namespace App\Models\Badges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeRedemptionItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'redemption_id', 'entitlement_id', 'badge_type_id', 'shortage_quantity',
    ];

    public function redemption(): BelongsTo
    {
        return $this->belongsTo(BadgeRedemption::class, 'redemption_id');
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(BadgeEntitlement::class, 'entitlement_id');
    }

    public function badgeType(): BelongsTo
    {
        return $this->belongsTo(BadgeType::class, 'badge_type_id');
    }
}
