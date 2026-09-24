<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FavoriteStudent extends Model
{
    protected $fillable = [
        'student_id',
        'teacher_id',
        'circle_id',
        'reason',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }
}
