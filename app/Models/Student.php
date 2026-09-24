<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Scopes\CenterScope;
use App\Models\CompetitionParticipant;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date_of_birth',
        'gender',
        'second_phone',
        'address',
        'guardian_id',
        'status',
        'suspended_at',
        'circle_id',
        'student_code',
        'education_type',
        'educational_stage',
        'school_grade',
        'previous_school',
        'center_entry_level',
        'join_date',
        'whatsapp_number',
        'health_status',
        'notes',
        'supervisor_id',
        'applicant',
        'center_id',
        'whatsapp_owner',
        'additional_contact_owner',
        'learning_difficulties',
        'personal_traits',
        'hobbies',
        'reading',
        'student_exit_status',
        'decision',
        'subscription_fees',
        'received_tools',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CenterScope());

        static::creating(function ($model) {
            if (!$model->center_id && auth()->check()) {
                $model->center_id = auth()->user()->center_id;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'date_of_birth'     => 'date',
            'suspended_at'      => 'datetime',
            'join_date'         => 'date',
            'hobbies'           => 'array',
            'subscription_fees' => 'float',
        ];
    }

    // ==========================================
    // Relationships
    // ==========================================

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'supervisor_id')
            ->withoutGlobalScope(CenterScope::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function constructionDetail(): HasOne
    {
        return $this->hasOne(StudentConstructionDetail::class, 'student_id');
    }

    public function itqanDetail(): HasOne
    {
        return $this->hasOne(StudentItqanDetail::class, 'student_id');
    }

    public function ibdaDetail(): HasOne
    {
        return $this->hasOne(StudentIbdaDetail::class, 'student_id');
    }

    public function favoritedBy(): HasMany
    {
        return $this->hasMany(FavoriteStudent::class);
    }

    public function isFavoritedBy(?int $teacherId): bool
    {
        return $this->favoritedBy()->where('teacher_id', $teacherId)->exists();
    }

    public function isFavoritedByCurrentUser(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // الأدوار الإدارية العليا تشوف أي طالب مفضّل، بغض النظر مين أضافه
        if ($user->hasRole(['admin', 'general_manager'])) {
            return $this->favoritedBy()->exists();
        }

        $teacherId = app(\App\Services\UserAccessService::class)->teacher($user)?->id;

        return $this->isFavoritedBy($teacherId);
    }

    /**
     * All weekly plans linked directly via student_id.
     */

    public function unpaidMonths(): HasOne
    {
        return $this->hasOne(StudentUnpaidMonths::class);
    }

    public function weeklyFollowups(): HasMany
    {
        return $this->hasMany(StudentWeeklyFollowup::class, 'student_id');
    }

    /**
     * Surah test results.
     */
    public function surahTestResults(): HasMany
    {
        return $this->hasMany(StudentSurahTestResult::class, 'student_id');
    }

    public function behavioralNotes(): HasMany
    {
        return $this->hasMany(BehavioralNote::class);
    }

    // ==========================================
    // Helpers
    // ==========================================

    public function mainTeacher(): ?Teacher
    {
        return $this->circle?->mainTeachers?->first();
    }

    // ==========================================
    // Accessors
    // ==========================================

    public function getOverdueMonthsCountAttribute(): int
    {
        if ($this->status === 'متوقف' && $this->suspended_at) {
            $endDate = $this->suspended_at->copy()->startOfMonth();
        } elseif ($this->status === 'مقيد') {
            $endDate = now()->startOfMonth();
        } else {
            return 0;
        }

        $startDate = $this->join_date
            ? $this->join_date->copy()->startOfMonth()
            : $this->created_at->copy()->startOfMonth();

        $paidKeys = $this->subscriptions
            ->where('status', 'مدفوع')
            ->pluck('month')
            ->map(fn($d) => $d->format('Y-m'))
            ->unique()
            ->toArray();

        $count = 0;
        $check = $startDate->copy();
        while ($check->lte($endDate)) {
            if (!in_array($check->format('Y-m'), $paidKeys)) {
                $count++;
            }
            $check->addMonth();
        }
        return $count;
    }

    public function getSuspendedPastDebtAttribute(): float
    {
        if ($this->status !== 'متوقف' || !$this->suspended_at) {
            return 0;
        }
        return (float) $this->subscriptions
            ->where('status', '!=', 'مدفوع')
            ->where('month', '<=', $this->suspended_at)
            ->sum('amount');
    }

    public function competitionParticipants(): HasMany
    {
        return $this->hasMany(CompetitionParticipant::class);
    }
}
