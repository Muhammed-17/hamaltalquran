<?php

namespace App\Models\Badges;

use App\Enums\Badges\CollectionRoundStatus;
use App\Models\Circle;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BadgeCollectionRound extends Model
{
    protected $fillable = [
        'circle_id', 'teacher_id', 'collection_date', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'status' => CollectionRoundStatus::class,
        'collection_date' => 'date',
    ];

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BadgeCollectionItem::class, 'round_id');
    }

    public function isDraft(): bool
    {
        return $this->status === CollectionRoundStatus::Draft;
    }

    public function isCompleted(): bool
    {
        return $this->status === CollectionRoundStatus::Completed;
    }
}
