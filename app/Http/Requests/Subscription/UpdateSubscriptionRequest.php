<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit subscriptions');
    }

    public function rules(): array
    {
        return [
            'student_id'     => 'required|exists:students,id',
            'circle_id'      => 'required|exists:circles,id',
            'teacher_id'     => 'required|exists:users,id',
            'amount'         => [
                'required_if:status,مدفوع',
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    // ✅ «مدفوع» بمبلغ صفر سجل دفع وهمي — المدفوع يجب أن يحمل مبلغًا موجبًا
                    if ($this->status === 'مدفوع' && $value !== null && (float) $value <= 0) {
                        $fail('المبلغ يجب أن يكون أكبر من صفر عند حالة مدفوع.');
                    }
                },
            ],
            'month'          => 'required|date_format:Y-m',
            'status'         => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\SubscriptionStatus::class)],
            'payment_method' => 'required_if:status,مدفوع|nullable|in:نقدي,تحويل بنكي,أخرى',
            'collected_by'   => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) {
                    // ✅ المحصِّل يجب أن يكون من طاقم التحصيل — كان يكفي وجود أي
                    // مستخدم، فأمكن إسناد قبض مبلغ لحساب ولي أمر
                    $collector = \App\Models\User::find($value);
                    if (!$collector || !$collector->hasAnyRole(['admin', 'teacher', 'supervisor', 'manager', 'general_manager'])) {
                        $fail('المحصِّل يجب أن يكون من طاقم التحصيل (معلم أو مشرف أو مدير).');
                    }
                },
            ],
            'notes'          => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required'        => 'الطالب مطلوب',
            'student_id.exists'          => 'الطالب غير موجود',
            'circle_id.required'         => 'الحلقة مطلوبة',
            'circle_id.exists'           => 'الحلقة غير موجودة',
            'teacher_id.required'        => 'المعلم مطلوب',
            'teacher_id.exists'          => 'المعلم غير موجود',
            'amount.required_if'         => 'المبلغ مطلوب عند حالة مدفوع',
            'amount.numeric'             => 'المبلغ يجب أن يكون رقم',
            'amount.min'                 => 'المبلغ يجب أن يكون 0 على الأقل',
            'month.required'             => 'الشهر مطلوب',
            'month.date_format'          => 'صيغة الشهر يجب أن تكون Y-m',
            'status.required'            => 'الحالة مطلوبة',
            'status.in'                  => 'حالة السداد غير صحيحة',
            'payment_method.required_if' => 'طريقة الدفع مطلوبة عند حالة مدفوع',
            'payment_method.in'          => 'طريقة الدفع غير صحيحة',
            'collected_by.exists'        => 'الموظف المحدد الذي قام بالتحصيل غير مسجل في النظام.',
        ];
    }
}
