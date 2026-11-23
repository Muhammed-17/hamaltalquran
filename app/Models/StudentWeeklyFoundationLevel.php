<?php

namespace App\Models;

use App\Models\Scopes\CenterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentWeeklyFoundationLevel extends Model
{
    protected $table = 'student_weekly_foundation_levels';

    protected $fillable = [
        'weekly_followup_id',
        'level',
        'notes',
    ];

    protected $casts = [
        'level' => \App\Enums\AssessmentLevel::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CenterScope());
    }

    /**
     * Get the weekly follow-up that owns this foundation level.
     */
    public function weeklyFollowup(): BelongsTo
    {
        return $this->belongsTo(StudentWeeklyFollowup::class, 'weekly_followup_id');
    }
}
