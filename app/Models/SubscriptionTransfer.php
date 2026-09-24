<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'circle_id',
        'month',
        'from_user_id',
        'to_user_id',
        'performed_by',
        'mode',
        'subscription_ids',
        'subscriptions_count',
        'notes',
    ];

    protected $casts = [
        'subscription_ids' => 'array',
        'month'            => 'date',
    ];

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
        public function circle()
    {
        return $this->belongsTo(Circle::class);
    }
}
