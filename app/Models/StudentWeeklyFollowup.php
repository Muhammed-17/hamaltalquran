<?php

namespace App\Models;

use App\Models\Scopes\CenterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StudentWeeklyFollowup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */

    protected $fillable = [
        'batch_id',
        'plan_type',
        'center_id',
        'circle_id',
        'student_id',
        'teacher_id',
        'week_start',
        'week_end',
        'study_days',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'study_days' => 'array',
        'week_start' => 'date',
        'week_end'   => 'date',
    ];

    // =====================================================
    // Boot
    // =====================================================

    protected static function booted(): void
    {
        static::addGlobalScope(new CenterScope());

        static::creating(function (self $model) {
            if (empty($model->batch_id)) {
                $model->batch_id = (string) Str::uuid();
            }

            if (!$model->center_id && auth()->check()) {
                $model->center_id = auth()->user()->center_id;
            }
        });
    }

    // =====================================================
    // Scopes
    // =====================================================

    public function scopeByBatch($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    public function scopeGroupPlans($query)
    {
        return $query->where('plan_type', 'group');
    }

    public function scopeIndividualPlans($query)
    {
        return $query->where('plan_type', 'individual');
    }

    public function scopeForWeek($query, string $weekStart)
    {
        // whereDate لا where: على SQLite تُخزن التواريخ كنصوص datetime
        // فتفشل مساواة النص الحرفية، بينما على MySQL العمود DATE أصلًا.
        return $query->whereDate('week_start', $weekStart);
    }

    // =====================================================
    // Relationships
    // =====================================================

    /**
     * Student.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Circle.
     */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /**
     * Teacher.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
    /**
     * New memorization.
     */
    public function newMemorizations(): HasOne
    {
        return $this->hasOne(StudentWeeklyNewMemorization::class, 'weekly_followup_id');
    }

    /**
     * Revision.
     */
    public function revisions(): HasOne
    {
        return $this->hasOne(StudentWeeklyRevision::class, 'weekly_followup_id');
    }

    /**
     * Old memorization.
     */
    public function oldMemorizations(): HasOne
    {
        return $this->hasOne(StudentWeeklyOldMemorization::class, 'weekly_followup_id');
    }
    /**
     * Discipline assessment.
     */
    public function discipline(): HasOne
    {
        return $this->hasOne(StudentWeeklyDiscipline::class, 'weekly_followup_id');
    }

    /**
     * Tajweed assessment.
     */
    public function tajweedAssessment(): HasOne
    {
        return $this->hasOne(StudentWeeklyTajweedAssessment::class, 'weekly_followup_id');
    }

    /**
     * Educational lesson assessment (student evaluation).
     */
    public function educationalLessonAssessment(): HasOne
    {
        return $this->hasOne(StudentWeeklyEducationalLesson::class, 'weekly_followup_id');
    }

    /**
     * Foundation level assessment.
     */
    public function foundationLevel(): HasOne
    {
        return $this->hasOne(StudentWeeklyFoundationLevel::class, 'weekly_followup_id');
    }


    public function activities(): HasMany
    {
        return $this->hasMany(StudentActivity::class, 'weekly_followup_id');
    }

    public function recommendation(): HasOne
    {
        return $this->hasOne(Recommendation::class, 'weekly_followup_id');
    }
}
