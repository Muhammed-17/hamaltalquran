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
            'badge_type_id'    => ['required', Rule::exists('badge_types', 'id')->where('is_active', true)],
            'quantity'         => ['required', 'integer', 'min:1'],
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
            'badge_type_id.required' => 'يجب اختيار نوع الوسام.',
            'badge_type_id.exists'   => 'نوع الوسام المحدد غير موجود أو غير مفعّل.',
            'quantity.min'           => 'الكمية يجب أن تكون أكبر من صفر.',
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
        });
    }
}
