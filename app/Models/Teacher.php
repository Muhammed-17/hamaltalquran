<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\SurahTest;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $branch_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Circle> $circles
 * @property-read int|null $circles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Branch|null $branch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 */
class Teacher extends Model
{
    protected $fillable = [
        'mobile_teacher',
        'user_id',
        'branch_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\CenterScope());
    }

    /**
     * الفرع اللي المعلم تابع له مباشرة (branch_id على teachers).
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * الفروع اللي المعلم مشرف عليها (many-to-many عبر branch_teacher).
     * معلم واحد ممكن يشرف على أكتر من فرع.
     */
    public function supervisedBranches()
    {
        return $this->belongsToMany(Branch::class, 'branch_teacher')
            ->withTimestamps();
    }

    // علاقة: المعلم ← مستخدمه
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ✅ توافق خلفي: name بقى معتمد على users.name
    public function getNameAttribute(): ?string
    {
        return $this->user?->name;
    }

    public function favoriteStudents(): HasMany
    {
        return $this->hasMany(FavoriteStudent::class);
    }

    // علاقة: المعلم ←→ حلقاته (Many-to-Many)
    public function circles()
    {
        return $this->belongsToMany(Circle::class, 'circle_teacher')->withPivot('role');
    }

    public function surahTests(): HasMany
    {
        return $this->hasMany(SurahTest::class);
    }
}
