<?php

namespace App\Http\Requests\Badges;

use App\Models\Badges\BadgeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBadgeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BadgeType::class);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255', Rule::unique('badge_types', 'name')],
            'description' => ['nullable', 'string'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الوسام مطلوب.',
            'name.unique'   => 'يوجد نوع وسام بهذا الاسم بالفعل.',
        ];
    }
}
