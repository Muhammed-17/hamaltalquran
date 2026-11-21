<?php

namespace App\Models\Badges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BadgeType extends Model
{
    protected $fillable = ['name', 'description', 'image', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function stockMovements(): HasMany
    {
        return $this->hasMany(BadgeStockMovement::class);
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(BadgeTeacherHandover::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(BadgeEntitlement::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(BadgeDistribution::class);
    }

    public function collectionItems(): HasMany
    {
        return $this->hasMany(BadgeCollectionItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
