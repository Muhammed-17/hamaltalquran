<?php

namespace App\Models;

use App\Models\Scopes\CenterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use \App\Services\UserAccessService;

class EducationalLesson extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'educational_lessons';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'center_id',
        'title',
        'description',
        'created_by',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CenterScope());

        static::creating(function ($model) {
            if (!$model->center_id && auth()->check()) {
                $access  = app(UserAccessService::class);
                $teacher = $access->teacher(auth()->user());
                // مركز المعلم يُقرأ عبر فرعه — لا توجد خاصية center_id على Teacher
                $model->center_id = $access->teacherCenterId($teacher);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Center that owns this lesson.
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * User who created the lesson.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Weekly assessments that used this lesson.
     *
     * الربط الفعلي يعيش في جدول student_weekly_educational_lessons —
     * جدول المتابعات نفسه لا يحتوي عمود educational_lesson_id.
     */
    public function weeklyAssessments(): HasMany
    {
        return $this->hasMany(StudentWeeklyEducationalLesson::class, 'educational_lesson_id');
    }
}
