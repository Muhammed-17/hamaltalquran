<?php

namespace App\Models;

use App\Models\Scopes\CenterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentWeeklyDiscipline extends Model
{
    protected $table = 'student_weekly_disciplines';

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
     * Get the weekly follow-up that owns this discipline record.
     */
    public function weeklyFollowup(): BelongsTo
    {
        return $this->belongsTo(StudentWeeklyFollowup::class, 'weekly_followup_id');
    }
}
