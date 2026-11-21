<?php

namespace App\Models\Badges;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeTeacherHandover extends Model
{
    protected $fillable = [
        'teacher_id', 'badge_type_id', 'quantity', 'handover_date',
        'notes', 'status', 'cancelled_by', 'cancelled_at', 'created_by',
    ];

    protected $casts = [
        'handover_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
