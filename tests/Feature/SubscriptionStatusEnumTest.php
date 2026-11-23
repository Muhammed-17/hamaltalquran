<?php

/**
 * حالة الاشتراك كـ enum: التحقق يرفض الخارج عن (مدفوع/غير مدفوع/معفي)،
 * والقراءة ترجع حالات، وفحص «قابل للتحويل» السماتي يعمل بالحالات.
 */

use App\Enums\SubscriptionStatus;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Models\Subscription;
use Illuminate\Support\Facades\Validator;

test('subscription status enum: values, validation and attribute checks', function () {
    expect(SubscriptionStatus::values())->toBe(['مدفوع', 'غير مدفوع', 'معفي'])
        ->and(SubscriptionStatus::settled())->toBe([SubscriptionStatus::Paid, SubscriptionStatus::Exempt]);

    $rule = (new StoreSubscriptionRequest)->rules()['status'];
    expect(Validator::make(['status' => 'مدفوع'], ['status' => $rule])->passes())->toBeTrue()
        ->and(Validator::make(['status' => 'مؤجل'], ['status' => $rule])->fails())->toBeTrue();

    $sub = new Subscription;
    $sub->setAttribute('status', 'معفي');
    expect($sub->status)->toBe(SubscriptionStatus::Exempt)
        ->and(in_array($sub->status, SubscriptionStatus::settled()))->toBeTrue();
});
