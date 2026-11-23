<?php

namespace App\Http\Requests\Badges;

use App\Models\Badges\BadgeEntitlement;
use App\Models\Badges\BadgeType;
use App\Models\Student;
use App\Models\User;
use App\Services\Badges\BadgeAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * حفظ صفحة التسليم اليومي: الصفوف المعدّلة فقط تصل هنا
 * (rows[student_id][badge_type_id] = إجمالي كمية اليوم المستهدفة).
 */
class StoreBadgeDailyEntitlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BadgeEntitlement::class);
    }

    public function rules(): array
    {
        return [
            'date'       => ['required', 'date'],
            'circle_id'  => ['required', Rule::exists('circles', 'id')],
            'teacher_id' => ['required', Rule::exists('users', 'id')],
            'instant'    => ['nullable', 'boolean'],
            'stay'       => ['nullable', 'boolean'],
            'order'      => ['nullable', 'string'],
            'rows'       => ['nullable', 'array'],
            'rows.*.*'   => ['nullable', 'integer', 'min:0', 'max:100000'],
            'deliver'    => ['nullable', 'array'],
            'deliver.*.*' => ['nullable', 'integer', 'min:0', 'max:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required'    => 'تاريخ اليوم مطلوب.',
            'circle_id.required' => 'يجب اختيار الحلقة.',
            'teacher_id.required' => 'يجب اختيار الموزّع.',
            'rows.*.*.integer' => 'الكمية يجب أن تكون رقمًا صحيحًا.',
            'rows.*.*.min'     => 'الكمية لا يمكن أن تكون سالبة.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $access = app(BadgeAccessService::class);
            $circleId = (int) $this->input('circle_id');
            $teacherId = (int) $this->input('teacher_id');

            // الحلقة داخل نطاق وصول المستخدم
            if (!$access->canAccessCircle($this->user(), $circleId)) {
                $v->errors()->add('circle_id', 'الحلقة المحددة خارج نطاق وصولك.');
            }

            // الموزّع مسؤول عن الحلقة حسب دوره (معلمها/مشرف فرعها/مدير مركزها/الإدارة)
            $distributor = User::find($teacherId);
            if (!$distributor || !$access->staffResponsibleForCircle($distributor, $circleId)) {
                $v->errors()->add('teacher_id', 'الموزّع المحدد ليس مسؤولًا عن هذه الحلقة.');
            }

            // كل طالب في الصفوف ينتمي للحلقة، وكل نوع مفعّل
            $activeTypeIds = BadgeType::active()->pluck('id')->map(fn ($i) => (int) $i)->all();
            foreach ((array) $this->input('rows', []) as $studentId => $typeQtys) {
                $student = Student::withoutGlobalScopes()->find((int) $studentId);
                if (!$student || (int) $student->circle_id !== $circleId) {
                    $v->errors()->add('rows', 'أحد الطلاب في الصفحة لا ينتمي إلى الحلقة المحددة.');
                    return;
                }
                foreach ((array) $typeQtys as $typeId => $qty) {
                    if (!in_array((int) $typeId, $activeTypeIds, true)) {
                        $v->errors()->add('rows', 'أحد أنواع الأوسمة غير موجود أو غير مفعّل.');
                        return;
                    }
                }
            }
        });
    }
}
