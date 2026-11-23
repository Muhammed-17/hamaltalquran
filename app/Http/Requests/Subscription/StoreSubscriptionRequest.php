<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create subscriptions');
    }

    public function rules(): array
    {
        return [
            'student_id'     => 'required|exists:students,id',
            'circle_id'      => 'required|exists:circles,id',
            'teacher_id'     => 'required|exists:users,id',
            'month'          => 'required|date_format:Y-m',
            'status'         => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\SubscriptionStatus::class)],
            'amount'         => 'required_if:status,مدفوع|nullable|numeric|min:0',
            'payment_method' => 'required_if:status,مدفوع|nullable|in:نقدي,تحويل بنكي,أخرى',
            'collected_by'   => 'nullable|exists:users,id',
            'notes'          => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'الطالب مطلوب',
            'student_id.exists'    => 'الطالب غير موجود',
            'circle_id.required'  => 'الحلقة مطلوبة',
            'circle_id.exists'    => 'الحلقة غير موجودة',
            'teacher_id.exists'   => 'المعلم غير موجود',
            'teacher_id.required'     => 'المعلم مطلوب',
            'amount.numeric'      => 'المبلغ يجب أن يكون رقم',
            'amount.min'          => 'المبلغ يجب أن يكون 0 على الأقل',
            'month.required'      => 'الشهر مطلوب',
            'month.date_format'   => 'صيغة الشهر يجب أن تكون Y-m',
            'status.required'     => 'الحالة مطلوبة',
            'status.in'           => 'حالة السداد غير صحيحة',
            'payment_method.in'   => 'طريقة الدفع غير صحيحة',
            'payment_method.required' => 'طريقة الدفع مطلوبة',
            'amount.required_if'         => 'المبلغ مطلوب عند حالة مدفوع',
            'amount.required'            => 'المبلغ مطلوب',
            'payment_method.required_if' => 'طريقة الدفع مطلوبة عند حالة مدفوع',
            'collected_by.exists'        => 'الموظف المحدد الذي قام بالتحصيل غير مسجل في النظام.',
        ];
    }
}
