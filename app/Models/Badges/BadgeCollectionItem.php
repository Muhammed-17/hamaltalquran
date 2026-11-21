<?php

namespace App\Models\Badges;

use App\Enums\Badges\CollectionItemStatus;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeCollectionItem extends Model
{
    protected $fillable = [
        'round_id', 'student_id', 'distribution_id', 'badge_type_id',
        'distributed_quantity', 'collected_quantity', 'status', 'notes',
    ];

    protected $casts = ['status' => CollectionItemStatus::class];

    public function round(): BelongsTo
    {
        return $this->belongsTo(BadgeCollectionRound::class, 'round_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(BadgeDistribution::class, 'distribution_id');
    }

    public function badgeType(): BelongsTo
    {
        return $this->belongsTo(BadgeType::class);
    }
}
