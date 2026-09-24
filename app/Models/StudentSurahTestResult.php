<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSurahTestResult extends Model
{
    use HasFactory;

    public const LEVELS = ['ممتاز', 'جيد جداً', 'جيد', 'مقبول','إعادة'];


    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'surah_test_id',
        'student_id',
        'prompt_errors',
        'tashkeel_errors',
        'percentage',
        'level',
        'notes',
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'prompt_errors'    => 'integer',
        'tashkeel_errors'  => 'integer',
        'percentage'       => 'integer',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    /**
     * Parent test.
     */
    public function surahTest(): BelongsTo
    {
        return $this->belongsTo(SurahTest::class);
    }

    /**
     * Student.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
