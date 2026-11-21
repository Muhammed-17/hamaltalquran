<?php

namespace App\Http\Requests\Badges;

use App\Enums\Badges\CollectionItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBadgeCollectionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('item'));
    }

    public function rules(): array
    {
        return [
            'collected_quantity' => ['required', 'integer', 'min:0'],
            'status'             => ['nullable', Rule::in(array_column(CollectionItemStatus::cases(), 'value'))],
            'notes'              => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'collected_quantity.required' => 'الكمية المحصلة مطلوبة.',
            'collected_quantity.min'      => 'الكمية المحصلة لا يمكن أن تكون سالبة.',
        ];
    }
}
