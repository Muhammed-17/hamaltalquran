<?php

namespace App\Models\Badges;

use App\Enums\Badges\ShortageStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BadgeShortage extends Model
{
    protected $fillable = [
        'entitlement_id', 'student_id', 'teacher_id', 'badge_type_id',
        'required_quantity', 'delivered_quantity', 'shortage_quantity', 'status',
    ];

    protected $casts = ['status' => ShortageStatus::class];

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

    public function distributions(): HasMany
    {
        return $this->hasMany(BadgeDistribution::class, 'shortage_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['pending', 'partial']);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [ShortageStatus::Pending, ShortageStatus::Partial], true);
    }
}
