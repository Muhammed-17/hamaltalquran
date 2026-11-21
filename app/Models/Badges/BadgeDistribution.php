<?php

namespace App\Models\Badges;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BadgeDistribution extends Model
{
    protected $fillable = [
        'entitlement_id', 'student_id', 'teacher_id', 'badge_type_id',
        'quantity', 'distribution_date', 'shortage_id', 'notes',
        'status', 'cancelled_by', 'cancelled_at', 'created_by',
    ];

    protected $casts = [
        'distribution_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(BadgeEntitlement::class, 'entitlement_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function badgeType(): BelongsTo
    {
        return $this->belongsTo(BadgeType::class);
    }

    public function shortage(): BelongsTo
    {
        return $this->belongsTo(BadgeShortage::class, 'shortage_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function collectionItems(): HasMany
    {
        return $this->hasMany(BadgeCollectionItem::class, 'distribution_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** الكمية المحصلة فعليًا من هذا التوزيع في جولات معتمدة */
    public function collectedQuantity(): int
    {
        return (int) $this->collectionItems()
            ->whereHas('round', fn ($q) => $q->where('status', 'completed'))
            ->sum('collected_quantity');
    }
}
