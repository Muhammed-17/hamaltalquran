<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Center;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Examiner;
use App\Models\Subscription;
use App\Models\CollectionRound;
use App\Models\Attendance;
use App\Models\CompetitionParticipant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property-read bool $is_online
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | العلاقات (Relations)
    |--------------------------------------------------------------------------
    */

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'guardian_id');
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function center()
    {
        return $this->belongsTo(Center::class, 'center_id');
    }

    public function examiner(): HasOne
    {
        return $this->hasOne(Examiner::class);
    }

    public function collectedSubscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'collected_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'user_id');
    }

    public function collectionRounds(): HasMany
    {
        return $this->hasMany(CollectionRound::class, 'created_by');
    }

    public function competitionParticipants(): HasMany
    {
        return $this->hasMany(CompetitionParticipant::class);
    }

    /*
    |--------------------------------------------------------------------------
    | الصفات المشتقة (Accessors)
    |--------------------------------------------------------------------------
    */

    /**
     * التحقق مما إذا كان المستخدم متصلاً الآن (خلال آخر 5 دقائق)
     */
    protected function isOnline(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->last_seen_at ? $this->last_seen_at->gt(now()->subMinutes(5)) : false,
        );
    }
}
