<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['student_id', 'date', 'status', 'notes', 'user_id'];

    protected $casts = [
        'date' => 'date',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\CenterScope());
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope: Present only
    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    // Scope: Absent only
    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    // Scope: Late only
    public function scopeLate($query)
    {
        return $query->where('status', 'late');
    }

    // Scope: Excused only
    public function scopeExcused($query)
    {
        return $query->where('status', 'excused');
    }

    // Scope: Date range
    public function scopeBetweenDates($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    // Scope: For specific student
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    // Accessor: Status label in Arabic
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'present' => 'حاضر',
            'absent' => 'غائب',
            'late' => 'متأخر',
            'excused' => 'بعذر',
            default => $this->status,
        };
    }

    // Accessor: Status color
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'present' => 'green',
            'absent' => 'red',
            'late' => 'yellow',
            'excused' => 'blue',
            default => 'gray',
        };
    }

    // Accessor: Status icon
    public function getStatusIconAttribute(): string
    {
        return match ($this->status) {
            'present' => 'check-circle',
            'absent' => 'x-circle',
            'late' => 'clock',
            'excused' => 'document-text',
            default => 'question-mark',
        };
    }
}
