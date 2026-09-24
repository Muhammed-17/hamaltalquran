<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class CompetitionParticipant
 *
 * Represents a participant registered in a competition.
 *
 * @property int $id
 * @property int $competition_id
 * @property int $competition_level_id
 * @property int|null $student_id
 * @property int|null $external_participant_id
 * @property int $registration_fee
 * @property int|null $tafsir_file_id
 * @property int $file_status
 * @property int|null $center_id
 * @property int|null $circle_id
 * @property int|null $supervisor_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CompetitionParticipant extends Model
{
    public const FILE_NOT_REQUIRED = 0;
    public const FILE_NOT_RECEIVED = 1;
    public const FILE_RECEIVED = 2;

    protected $fillable = [
        'competition_id',
        'competition_level_id',
        'center_id',
        'student_id',
        'external_participant_id',
        'circle_id',
        'file_status',
        'supervisor_id',
        'tafsir_file_id',
        'registration_fee',
    ];

    protected $casts = [
        'registration_fee' => 'integer',
        'file_status' => 'integer',
        'tafsir_file_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function competitionLevel(): BelongsTo
    {
        return $this->belongsTo(CompetitionLevel::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function externalParticipant(): BelongsTo
    {
        return $this->belongsTo(ExternalParticipant::class);
    }

    public function tafsirFile(): BelongsTo
    {
        return $this->belongsTo(TafsirFile::class, 'tafsir_file_id');
    }

    public function competitionAnswers(): HasMany
    {
        return $this->hasMany(CompetitionAnswer::class);
    }

    public function competitionResult(): HasOne
    {
        return $this->hasOne(CompetitionResult::class);
    }

    public function getParticipantNameAttribute(): ?string
    {
        return $this->student?->name
            ?? $this->externalParticipant?->name;
    }

    public function getParticipantTypeAttribute(): string
    {
        return $this->student_id !== null
            ? 'student'
            : 'external';
    }

    public function isInternal(): bool
    {
        return $this->student_id !== null;
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function isExternal(): bool
    {
        return $this->external_participant_id !== null;
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
