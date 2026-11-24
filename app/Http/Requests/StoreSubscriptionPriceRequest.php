<?php

namespace App\Http\Requests;

use App\Enums\EducationalStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'circle_level' => ['required', Rule::in(['build', 'mastery', 'creativity'])],
            'education_stage' => ['required', Rule::enum(EducationalStage::class)],
            'school_grade' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'circle_level.required' => 'مستوى الحلقة مطلوب',
            'circle_level.in' => 'مستوى الحلقة غير صحيح',
            'education_stage.enum' => 'المرحلة الدراسية غير صحيحة',
            'education_stage.required' => 'المستوى التعليمي مطلوب',
            'school_grade.string' => 'الصف الدراسي يجب أن يكون نص',
            'amount.required' => 'المبلغ مطلوب',
            'amount.numeric' => 'المبلغ يجب أن يكون رقم',
            'amount.min' => 'المبلغ يجب أن يكون 0 على الأقل',
        ];
    }
}
