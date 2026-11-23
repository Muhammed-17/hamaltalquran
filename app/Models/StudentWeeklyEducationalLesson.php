<?php

namespace App\Models;

use App\Models\Scopes\CenterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentWeeklyEducationalLesson extends Model
{
    protected $table = 'student_weekly_educational_lessons';

    protected $fillable = [
        'weekly_followup_id',
        'educational_lesson_id',
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
     * Get the weekly follow-up that owns this educational lesson.
     */
    public function weeklyFollowup(): BelongsTo
    {
        return $this->belongsTo(StudentWeeklyFollowup::class, 'weekly_followup_id');
    }

    /**
     * The educational lesson (title, description).
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(EducationalLesson::class, 'educational_lesson_id');
    }
}
