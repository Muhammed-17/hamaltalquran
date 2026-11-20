<?php

namespace App\Http\Requests\Competition;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompetitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['required', 'string', 'in:draft,active,closed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المسابقة مطلوب.',
            'name.string'   => 'اسم المسابقة يجب أن يكون نصًا.',
            'name.max'      => 'اسم المسابقة يجب ألا يتجاوز :max حرفًا.',

            'description.string' => 'الوصف يجب أن يكون نصًا.',

            'status.required' => 'حالة المسابقة مطلوبة.',
            'status.string'   => 'حالة المسابقة غير صالحة.',
            'status.in'       => 'حالة المسابقة المحددة غير صالحة.',
        ];
    }
}
