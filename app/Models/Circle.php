<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class Circle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'type',
        'level',
        'branch_id',
    ];

    // مصدر الحقيقة الوحيد للمركز أصبح عبر الفرع؛ عمود center_id اتشال نهائيًا من الجدول.
    // هذا الـ accessor للتوافق الخلفي المؤقت مع أي كود قديم بيستخدم $circle->center_id مباشرة
    // (لازم تُستبدل تدريجيًا بـ $circle->branch->center_id أو $circle->center).
    public function getCenterIdAttribute(): ?int
    {
        return $this->branch?->center_id;
    }

    // ─── العلاقات الجديدة ───
    public function groupSessionPlans(): HasMany
    {
        return $this->hasMany(GroupSessionPlan::class);
    }

    public function studentConstructionDetails(): HasMany
    {
        return $this->hasMany(StudentConstructionDetail::class);
    }

    // ─── العلاقات الموجودة ───
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'circle_teacher')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * مشرفو الحلقة أصبحوا يُشتقّون من مشرفي الفرع التابعة له الحلقة،
     * وليس من تخصيص فردي على مستوى الحلقة (role=supervisor في circle_teacher أصبح قديمًا/غير مستخدم).
     */
    public function supervisors(): BelongsToMany
    {
        // لو الحلقة معندهاش فرع (branch_id لسه NULL)، رجّع علاقة فاضية آمنة
        // بدل ما ننهار على null->supervisors().
        if (!$this->branch_id) {
            return $this->belongsToMany(Teacher::class, 'branch_teacher', 'branch_id', 'teacher_id')
                ->whereRaw('1 = 0');
        }

        return $this->branch->supervisors();
    }

    public function mainTeachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'circle_teacher')
            ->wherePivot('role', 'main')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function assistantTeachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'circle_teacher')
            ->wherePivot('role', 'assistant')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function getMainTeacherAttribute(): ?Teacher
    {
        return $this->mainTeachers->first();
    }

    public function getAssistantTeacherAttribute(): ?Teacher
    {
        return $this->assistantTeachers->first();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * المركز تبع الحلقة، عبر الفرع. للتوافق الخلفي مع أي كود قديم بيستخدم $circle->center.
     * ملحوظة: دي مش علاقة Eloquent حقيقية (hasOneThrough) عشان تفادي تعقيد إضافي؛
     * لو محتاج eager loading كفء استخدم ->with('branch.center') بدل ->with('center').
     */
    public function getCenterAttribute(): ?Center
    {
        return $this->branch?->center;
    }

    public function getLevelArabicAttribute(): string
    {
        return match ($this->level) {
            'build'      => 'بناء',
            'mastery'    => 'إتقان',
            'creativity' => 'إبداع',
            default      => $this->level,
        };
    }

    public function getTypeArabicAttribute(): string
    {
        return match ($this->type) {
            'group'      => 'جماعية',
            'individual' => 'فردية',
            default      => $this->type,
        };
    }

    public static function circleIdsForTeacher(int $teacherId, array $roles): Collection
    {
        return DB::table('circle_teacher')
            ->where('teacher_id', $teacherId)
            ->whereIn('role', $roles)
            ->pluck('circle_id');
    }

    public function surahTests(): HasMany
    {
        return $this->hasMany(SurahTest::class);
    }

    public function competitionParticipants(): HasMany
    {
        return $this->hasMany(CompetitionParticipant::class);
    }
}
