<?php

namespace App\Http\Requests\BehavioralNote;

use App\Models\Scopes\CenterScope;
use App\Models\Student;
use App\Services\UserAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Request: Update Behavioral Note
 */
class UpdateBehavioralNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id'     => ['sometimes', 'integer', 'exists:students,id'],
            'circle_id'      => ['sometimes', 'integer', 'exists:circles,id'],
            'teacher_id'     => ['sometimes', 'integer', 'exists:teachers,id'],
            'incident_at'    => ['sometimes', 'date'],
            'behavior'       => ['sometimes', 'string', 'max:2000'],
            'current_status' => ['nullable', 'string', 'max:2000'],
            'follow_up_at'   => ['nullable', 'date'],
        ];
    }

    /**
     * فحوص النطاق لأي معرفات مُرسلة: لا يجوز نقل الملاحظة إلى حلقة أو طالب
     * أو معلم خارج نطاق المستخدم عبر التعديل.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $access = app(UserAccessService::class);
            $user = $this->user();

            if ($this->filled('circle_id') && !$access->canAccessCircle($user, (int) $this->input('circle_id'))) {
                $v->errors()->add('circle_id', 'الحلقة المختارة خارج نطاق صلاحياتك.');
            }

            if ($this->filled('student_id')) {
                $student = Student::withoutGlobalScope(CenterScope::class)->find((int) $this->input('student_id'));
                if ($student && !$access->canAccessStudent($user, $student)) {
                    $v->errors()->add('student_id', 'الطالب المختار خارج نطاق صلاحياتك.');
                }
            }

            if ($this->filled('teacher_id') && !$access->accessibleTeachers($user)->whereKey((int) $this->input('teacher_id'))->exists()) {
                $v->errors()->add('teacher_id', 'المعلم المختار خارج نطاق صلاحياتك.');
            }
        });
    }

    public function messages(): array
    {
        return [
            // Student
            'student_id.integer'      => 'معرف الطالب يجب أن يكون رقمًا صحيحًا.',
            'student_id.exists'       => 'الطالب المحدد غير موجود.',

            // Circle
            'circle_id.integer'       => 'معرف الحلقة يجب أن يكون رقمًا صحيحًا.',
            'circle_id.exists'        => 'الحلقة المختارة غير موجودة.',

            // Teacher
            'teacher_id.integer'      => 'معرف المعلم يجب أن يكون رقمًا صحيحًا.',
            'teacher_id.exists'       => 'المعلم المحدد غير موجود.',

            // Incident Date
            'incident_at.date'        => 'تاريخ السلوك يجب أن يكون تاريخاً صحيحاً.',

            // Behavior
            'behavior.string'         => 'وصف السلوك يجب أن يكون نصاً.',
            'behavior.max'            => 'وصف السلوك يجب ألا يتجاوز 2000 حرف.',

            // Current Status
            'current_status.string'   => 'الإجراء المتخذ / الحالة الحالية يجب أن تكون نصاً.',
            'current_status.max'      => 'الإجراء المتخذ / الحالة الحالية يجب ألا تتجاوز 2000 حرف.',

            // Follow-up Date
            'follow_up_at.date'       => 'تاريخ المتابعة يجب أن يكون تاريخاً صحيحاً.',
        ];
    }
}
