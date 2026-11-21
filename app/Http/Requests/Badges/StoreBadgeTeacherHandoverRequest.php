<?php

namespace App\Http\Requests\Badges;

use App\Models\Badges\BadgeTeacherHandover;
use App\Services\Badges\BadgeAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBadgeTeacherHandoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BadgeTeacherHandover::class);
    }

    public function rules(): array
    {
        return [
            'teacher_id'    => ['required', Rule::exists('teachers', 'user_id')],
            'badge_type_id' => ['required', Rule::exists('badge_types', 'id')->where('is_active', true)],
            'quantity'      => ['required', 'integer', 'min:1'],
            'handover_date' => ['required', 'date'],
            'notes'         => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_id.required'    => 'يجب اختيار المعلم.',
            'teacher_id.exists'      => 'المعلم المحدد غير موجود.',
            'badge_type_id.required' => 'يجب اختيار نوع الوسام.',
            'badge_type_id.exists'   => 'نوع الوسام المحدد غير موجود أو غير مفعّل.',
            'quantity.min'           => 'الكمية يجب أن تكون أكبر من صفر.',
            'handover_date.required' => 'تاريخ التسليم مطلوب.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty() || !$this->filled('teacher_id')) {
                return;
            }
            if (!app(BadgeAccessService::class)->teacherInScope($this->user(), (int) $this->input('teacher_id'))) {
                $v->errors()->add('teacher_id', 'المعلم المحدد خارج نطاق وصولك.');
            }
        });
    }
}
