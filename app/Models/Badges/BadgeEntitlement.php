<?php

namespace App\Models\Badges;

use App\Enums\Badges\EntitlementStatus;
use App\Models\Circle;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BadgeEntitlement extends Model
{
    protected $fillable = [
        'student_id', 'circle_id', 'teacher_id', 'badge_type_id',
        'quantity', 'entitlement_date', 'status', 'notes', 'created_by', 'batch_id',
    ];

    protected $casts = [
        'status' => EntitlementStatus::class,
        'entitlement_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function badgeType(): BelongsTo
    {
        return $this->belongsTo(BadgeType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(BadgeDistribution::class, 'entitlement_id');
    }

    public function activeDistributions(): HasMany
    {
        return $this->distributions()->where('status', 'active');
    }

    public function shortage(): HasOne
    {
        return $this->hasOne(BadgeShortage::class, 'entitlement_id');
    }

    public function deliveredQuantity(): int
    {
        return (int) $this->activeDistributions()->sum('quantity');
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->deliveredQuantity());
    }
}
