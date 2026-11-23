<?php

namespace App\Http\Requests\Badges;

use App\Services\Badges\BadgeAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBadgeReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher']);
    }

    public function rules(): array
    {
        return [
            'teacher_id'    => ['required', Rule::exists('users', 'id')],
            'badge_type_id' => ['required', Rule::exists('badge_types', 'id')],
            'quantity'      => ['required', 'integer', 'min:1'],
            'notes'         => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_id.required'    => 'يجب اختيار المعلم.',
            'badge_type_id.required' => 'يجب اختيار نوع الوسام.',
            'quantity.min'           => 'الكمية يجب أن تكون أكبر من صفر.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty() || !$this->filled('teacher_id')) {
                return;
            }
            // الإرجاع يخصم من عهدة صاحبها: لا يجوز إلا من صاحب العهدة نفسه،
            // أو ممن هو أعلى منه في سلسلة العهدة وداخل نطاقه (يستطيع تسليمه) —
            // زميل بنفس الرتبة أو من هو أعلى من الفاعل لا يمس عهدة غيره.
            $access = app(BadgeAccessService::class);
            $actor = $this->user();
            $target = \App\Models\User::find((int) $this->input('teacher_id'));
            if (!$target || ($actor->id !== $target->id && !$access->canHandoverTo($actor, $target))) {
                $v->errors()->add('teacher_id', 'لا يمكنك الإرجاع من عهدة هذا المستخدم.');
            }
        });
    }
}
