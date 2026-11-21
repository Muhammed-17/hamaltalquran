<?php

namespace App\Http\Requests\Badges;

use Illuminate\Foundation\Http\FormRequest;

class ResolveBadgeShortageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('resolve', $this->route('shortage'));
    }

    public function rules(): array
    {
        return [
            'quantity'          => ['required', 'integer', 'min:1'],
            'distribution_date' => ['required', 'date'],
            'notes'             => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر.',
            'distribution_date.required' => 'تاريخ التسليم مطلوب.',
        ];
    }
}
