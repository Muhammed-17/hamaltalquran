<?php

namespace App\Models;
use App\Models\Scopes\CenterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentWeeklyNewMemorization extends Model
{
    protected $table = 'student_weekly_new_memorizations';

    protected $fillable = [
        'weekly_followup_id',
        'plan_from_surah_id',
        'plan_from_ayah',
        'plan_to_surah_id',
        'plan_to_ayah',
        'plan_comparison',
        'progress_difference',
        'average_level',
        'notes',
    ];

    protected $casts = [
        'average_level' => \App\Enums\AssessmentLevel::class,
        'plan_from_ayah' => 'integer',
        'plan_to_ayah'   => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CenterScope());
    }

    public function weeklyFollowup(): BelongsTo
    {
        return $this->belongsTo(StudentWeeklyFollowup::class, 'weekly_followup_id');
    }

    public function fromSurah(): BelongsTo
    {
        return $this->belongsTo(Surah::class, 'plan_from_surah_id');
    }

    public function toSurah(): BelongsTo
    {
        return $this->belongsTo(Surah::class, 'plan_to_surah_id');
    }
}
