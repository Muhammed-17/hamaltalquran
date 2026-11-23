<?php

namespace App\Http\Requests\BehavioralNote;

use App\Models\Student;
use App\Services\UserAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Request: Store Behavioral Note
 */
class StoreBehavioralNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id'     => ['required', 'integer', 'exists:students,id'],
            'circle_id'      => ['required', 'integer', 'exists:circles,id'],
            'teacher_id'     => ['required', 'integer', 'exists:teachers,id'],
            'incident_at'    => ['required', 'date'],
            'behavior'       => ['required', 'string', 'max:2000'],
            'current_status' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * فحوص النطاق: الحلقة داخل نطاق المستخدم، والطالب مسجل في تلك الحلقة،
     * والمعلم ضمن المعلمين المتاحين له — الوجود وحده (exists) لا يكفي.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $access = app(UserAccessService::class);
            $user = $this->user();
            $circleId = (int) $this->input('circle_id');
            $studentId = (int) $this->input('student_id');
            $teacherId = (int) $this->input('teacher_id');

            if (!$access->canAccessCircle($user, $circleId)) {
                $v->errors()->add('circle_id', 'الحلقة المختارة خارج نطاق صلاحياتك.');
            }

            $student = Student::withoutGlobalScope(\App\Models\Scopes\CenterScope::class)->find($studentId);
            if ($student && (int) $student->circle_id !== $circleId) {
                $v->errors()->add('student_id', 'الطالب المختار ليس مسجلًا في الحلقة المختارة.');
            }

            if (!$access->accessibleTeachers($user)->whereKey($teacherId)->exists()) {
                $v->errors()->add('teacher_id', 'المعلم المختار خارج نطاق صلاحياتك.');
            }
        });
    }

    public function messages(): array
    {
        return [
            // Student
            'student_id.required'     => 'يرجى اختيار الطالب.',
            'student_id.integer'      => 'معرف الطالب يجب أن يكون رقمًا صحيحًا.',
            'student_id.exists'        => 'الطالب المحدد غير موجود.',

            // Circle
            'circle_id.required'      => 'يرجى اختيار الحلقة.',
            'circle_id.integer'       => 'معرف الحلقة يجب أن يكون رقمًا صحيحًا.',
            'circle_id.exists'        => 'الحلقة المختارة غير موجودة.',

            // Teacher
            'teacher_id.required'     => 'يرجى اختيار المعلم.',
            'teacher_id.integer'      => 'معرف المعلم يجب أن يكون رقمًا صحيحًا.',
            'teacher_id.exists'        => 'المعلم المحدد غير موجود.',

            // Incident Date
            'incident_at.required'    => 'يرجى تحديد تاريخ السلوك / الواقعة.',
            'incident_at.date'        => 'تاريخ السلوك يجب أن يكون تاريخاً صحيحاً.',

            // Behavior
            'behavior.required'       => 'يرجى إدخال وصف السلوك.',
            'behavior.string'         => 'وصف السلوك يجب أن يكون نصاً.',
            'behavior.max'            => 'وصف السلوك يجب ألا يتجاوز 2000 حرف.',

            // Current Status
            'current_status.string'   => 'الإجراء المتخذ / الحالة الحالية يجب أن تكون نصاً.',
            'current_status.max'      => 'الإجراء المتخذ / الحالة الحالية يجب ألا تتجاوز 2000 حرف.',
        ];
    }
}