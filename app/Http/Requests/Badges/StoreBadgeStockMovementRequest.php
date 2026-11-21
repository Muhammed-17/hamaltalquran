<?php

namespace App\Http\Requests\Badges;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBadgeStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // الصلاحية حسب إشارة الكمية: موجبة (إضافة) ← stock.add، سالبة (تسوية) ← stock.adjust
        return (int) $this->input('quantity') < 0
            ? $this->user()->can('badges.stock.adjust')
            : $this->user()->can('badges.stock.add');
    }

    public function rules(): array
    {
        $isDeduction = (int) $this->input('quantity') < 0;

        return [
            'badge_type_id' => ['required', Rule::exists('badge_types', 'id')],
            'quantity'      => ['required', 'integer', 'not_in:0'],
            'notes'         => $isDeduction ? ['required', 'string'] : ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'badge_type_id.required' => 'يجب اختيار نوع الوسام.',
            'badge_type_id.exists'   => 'نوع الوسام المحدد غير موجود.',
            'quantity.required'      => 'الكمية مطلوبة.',
            'quantity.not_in'        => 'الكمية لا يمكن أن تكون صفرًا.',
            'notes.required'         => 'التسوية بالخصم تتطلب توثيق السبب.',
        ];
    }
}
