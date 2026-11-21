<?php

namespace App\Http\Requests\Badges;

use App\Models\Badges\BadgeTeacherHandover;
use App\Models\Badges\BadgeType;
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
            // كميات كل الأنواع دفعة واحدة: quantities[نوع] = الكمية (صفر/فارغ = لا تسليم لهذا النوع)
            'quantities'    => ['required', 'array'],
            'quantities.*'  => ['nullable', 'integer', 'min:0'],
            'handover_date' => ['required', 'date'],
            'notes'         => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_id.required'    => 'يجب اختيار المعلم.',
            'teacher_id.exists'      => 'المعلم المحدد غير موجود.',
            'quantities.required'    => 'أدخل كميات الأوسمة المطلوب تسليمها.',
            'quantities.*.integer'   => 'الكمية يجب أن تكون رقمًا صحيحًا.',
            'quantities.*.min'       => 'الكمية لا يمكن أن تكون سالبة.',
            'handover_date.required' => 'تاريخ التسليم مطلوب.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            if ($this->filled('teacher_id')
                && !app(BadgeAccessService::class)->teacherInScope($this->user(), (int) $this->input('teacher_id'))) {
                $v->errors()->add('teacher_id', 'المعلم المحدد خارج نطاق وصولك.');
            }

            $quantities = (array) $this->input('quantities', []);
            $activeTypeIds = BadgeType::active()->pluck('id')->all();

            foreach (array_keys($quantities) as $typeId) {
                if (!in_array((int) $typeId, $activeTypeIds, true)) {
                    $v->errors()->add('quantities', 'أحد أنواع الأوسمة المحددة غير موجود أو غير مفعّل.');
                    return;
                }
            }

            if (collect($quantities)->filter(fn ($q) => (int) $q > 0)->isEmpty()) {
                $v->errors()->add('quantities', 'أدخل كمية أكبر من صفر لنوع واحد على الأقل.');
            }
        });
    }
}
