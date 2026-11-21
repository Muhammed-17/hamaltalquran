<?php

namespace App\Models\Badges;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeTeacherHandover extends Model
{
    protected $fillable = [
        'teacher_id', 'source_user_id', 'badge_type_id', 'quantity', 'handover_date',
        'notes', 'status', 'cancelled_by', 'cancelled_at', 'created_by',
    ];

    protected $casts = [
        'handover_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    /** المستلم (صاحب العهدة الجديد) — أي مستخدم طاقم، لا معلم فقط */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** المُسلِّم من عهدته الشخصية — null تعني التسليم من الإدارة المركزية */
    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function isFromAdmin(): bool
    {
        return $this->source_user_id === null;
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
