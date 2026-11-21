<?php

namespace App\Http\Requests\Badges;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBadgeEntitlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('entitlement'));
    }

    public function rules(): array
    {
        return [
            'quantity'         => ['required', 'integer', 'min:1'],
            'entitlement_date' => ['required', 'date'],
            'notes'            => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر.',
            'entitlement_date.required' => 'تاريخ الاستحقاق مطلوب.',
        ];
    }
}
