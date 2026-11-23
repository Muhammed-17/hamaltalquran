<?php

namespace App\Models\Badges;

use App\Enums\Badges\RedemptionStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BadgeRedemption extends Model
{
    protected $fillable = [
        'student_id', 'period_month', 'redemption_date', 'status', 'notes', 'created_by',
        'collection_round_id',
    ];

    protected $casts = [
        'status' => RedemptionStatus::class,
        'redemption_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BadgeRedemptionItem::class, 'redemption_id');
    }

    /** جولة التحصيل التي نُفّذ الخصم ضمنها (إن وُجدت) */
    public function collectionRound(): BelongsTo
    {
        return $this->belongsTo(BadgeCollectionRound::class, 'collection_round_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', RedemptionStatus::Completed->value);
    }

    public function isCompleted(): bool
    {
        return $this->status === RedemptionStatus::Completed;
    }

    public function totalShortage(): int
    {
        return (int) $this->items->sum('shortage_quantity');
    }

    /** إجمالي ما استُهلك من المستحق غير المسلّم */
    public function totalConsumed(): int
    {
        return $this->totalShortage();
    }
}
