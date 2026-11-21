<?php

namespace App\Http\Requests\Badges;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBadgeCollectionRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('collection'));
    }

    public function rules(): array
    {
        return [
            'collection_date' => ['required', 'date'],
            'notes'           => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return ['collection_date.required' => 'تاريخ التحصيل مطلوب.'];
    }
}
