<?php

namespace App\Http\Requests\Badges;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب إلغاء عملية أوسمة (تسليم/استحقاق/توزيع/جولة).
 * التفويض نفسه يتم عبر Policy المورد في المتحكم؛ هنا التحقق من سبب الإلغاء فقط.
 */
class CancelBadgeOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['reason.max' => 'سبب الإلغاء طويل جدًا.'];
    }
}
