<?php

namespace App\Http\Requests\Badges;

use App\Models\Badges\BadgeEntitlement;
use App\Models\Student;
use App\Services\Badges\BadgeAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBadgeEntitlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BadgeEntitlement::class);
    }

    public function rules(): array
    {
        return [
            'student_id'       => ['required', Rule::exists('students', 'id')],
            'circle_id'        => ['required', Rule::exists('circles', 'id')],
            // المسؤول عن التوزيع أي مستخدم طاقم (معلم/مشرف/مدير) — المسؤولية في withValidator
            'teacher_id'       => ['required', Rule::exists('users', 'id')],
            // الصفحة الموحدة: كمية لكل نوع (صفر/فارغ = لا استحقاق لهذا النوع)
            'quantities'       => ['required', 'array'],
            'quantities.*'     => ['nullable', 'integer', 'min:0'],
            'instant'          => ['nullable', 'boolean'],
            'entitlement_date' => ['required', 'date'],
            'notes'            => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required'    => 'يجب اختيار الطالب.',
            'circle_id.required'     => 'يجب اختيار الحلقة.',
            'teacher_id.required'    => 'يجب اختيار المعلم.',
            'quantities.required'    => 'أدخل كمية لنوع واحد على الأقل.',
            'quantities.*.integer'   => 'الكمية يجب أن تكون رقمًا صحيحًا.',
            'quantities.*.min'       => 'الكمية لا يمكن أن تكون سالبة.',
            'entitlement_date.required' => 'تاريخ الاستحقاق مطلوب.',
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
            $studentId = (int) $this->input('student_id');
            $teacherId = (int) $this->input('teacher_id');

            // الطالب ينتمي للحلقة المختارة
            $student = Student::withoutGlobalScopes()->find($studentId);
            if ($student && (int) $student->circle_id !== $circleId) {
                $v->errors()->add('student_id', 'الطالب المحدد لا ينتمي إلى الحلقة المختارة.');
            }

            // الحلقة داخل نطاق وصول المستخدم
            if (!$access->canAccessCircle($this->user(), $circleId)) {
                $v->errors()->add('circle_id', 'الحلقة المحددة خارج نطاق وصولك.');
            }

            // المسؤول مسؤول عن الحلقة حسب دوره (معلمها/مشرف فرعها/مدير مركزها/الإدارة)
            $staff = \App\Models\User::find($teacherId);
            if (!$staff || !$access->staffResponsibleForCircle($staff, $circleId)) {
                $v->errors()->add('teacher_id', 'المسؤول المحدد ليس مسؤولًا عن هذه الحلقة.');
            }

            // مفاتيح الكميات أنواع مفعّلة فقط، وواحد على الأقل بكمية موجبة
            $quantities = (array) $this->input('quantities', []);
            $activeTypeIds = \App\Models\Badges\BadgeType::active()->pluck('id')->map(fn ($i) => (int) $i)->all();
            $hasPositive = false;
            foreach ($quantities as $typeId => $quantity) {
                if (!in_array((int) $typeId, $activeTypeIds, true)) {
                    $v->errors()->add("quantities.{$typeId}", 'نوع الوسام غير موجود أو غير مفعّل.');
                }
                if ((int) $quantity > 0) {
                    $hasPositive = true;
                }
            }
            if (!$hasPositive) {
                $v->errors()->add('quantities', 'أدخل كمية أكبر من صفر لنوع واحد على الأقل.');
            }
        });
    }
}
