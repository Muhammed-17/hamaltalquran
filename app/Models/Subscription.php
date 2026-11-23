<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $student_id
 * @property int $circle_id
 * @property int|null $collected_by
 * @property numeric $amount
 * @property \Illuminate\Support\Carbon $month
 * @property string $status
 * @property string|null $payment_method
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property bool $is_collected
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Circle $circle
 * @property-read \App\Models\User|null $collectedBy
 * @property-read \App\Models\Student $student
 * @property-read \App\Models\CollectionRoundItem|null $collectionRoundItem
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCircleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCollectedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereUpdatedAt($value)
 */
class Subscription extends Model
{
    protected $fillable = [
        'student_id',
        'circle_id',
        'teacher_id',
        'collected_by',
        'amount',
        'month',
        'status',
        'payment_method',
        'paid_at',
        'is_collected',
        'notes'
    ];

    protected $casts = [
        'status' => \App\Enums\SubscriptionStatus::class,
        'month' => 'date:Y-m',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
        'is_collected' => 'boolean',
    ];


    // علاقة: الاشتراك ← طالبه
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // علاقة: الاشتراك ← حلقتِه
    public function circle()
    {
        return $this->belongsTo(Circle::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    // علاقة: الاشتراك ← من استلمه (مستخدم: معلم/مشرف/مدير)
    public function collectedBy()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    // علاقة: الاشتراك ← عنصر التحصيل التحصيل (إن وُجد)
    public function collectionRoundItem()
    {
        return $this->hasOne(CollectionRoundItem::class);
    }

    // نطاق: الاشتراكات المدفوعة غير المرتبطة بأي التحصيل بعد
    public function scopeUncollected($query)
    {
        return $query->where('is_collected', false)->where('status', \App\Enums\SubscriptionStatus::Paid);
    }

    // دالة حساب المبلغ (تُستخدم عند الإنشاء)
    public static function calculateAmount(Student $student, Circle $circle): float
    {
        $price = SubscriptionPrice::where('circle_level', $circle->level)
            ->where('education_stage', $student->educational_stage)
            ->first();
        return $price?->amount ?? 60.00;
    }
}
