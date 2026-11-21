<?php

namespace App\Http\Requests\Badges;

use App\Enums\Badges\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBadgeStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // صلاحية الحركة حسب نوعها: إضافة/افتتاحي ← stock.add، تسوية ← stock.adjust
        return match ($this->input('movement_type')) {
            StockMovementType::Adjustment->value => $this->user()->can('badges.stock.adjust'),
            default => $this->user()->can('badges.stock.add'),
        };
    }

    public function rules(): array
    {
        $isAdjustment = $this->input('movement_type') === StockMovementType::Adjustment->value;

        return [
            'badge_type_id' => ['required', Rule::exists('badge_types', 'id')],
            'movement_type' => ['required', Rule::in([
                StockMovementType::InitialStock->value,
                StockMovementType::Addition->value,
                StockMovementType::Adjustment->value,
            ])],
            'quantity'      => $isAdjustment
                ? ['required', 'integer', 'not_in:0']
                : ['required', 'integer', 'min:1'],
            'notes'         => $isAdjustment ? ['required', 'string'] : ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'badge_type_id.required' => 'يجب اختيار نوع الوسام.',
            'badge_type_id.exists'   => 'نوع الوسام المحدد غير موجود.',
            'movement_type.required' => 'يجب اختيار نوع الحركة.',
            'quantity.required'      => 'الكمية مطلوبة.',
            'quantity.min'           => 'الكمية يجب أن تكون أكبر من صفر.',
            'quantity.not_in'        => 'كمية التسوية لا يمكن أن تكون صفرًا.',
            'notes.required'         => 'يجب توثيق سبب التسوية.',
        ];
    }
}
