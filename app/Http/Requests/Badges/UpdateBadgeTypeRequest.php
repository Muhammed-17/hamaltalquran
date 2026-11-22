<?php

namespace App\Http\Requests\Badges;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBadgeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('type'));
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255', Rule::unique('badge_types', 'name')->ignore($this->route('type')->id)],
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
