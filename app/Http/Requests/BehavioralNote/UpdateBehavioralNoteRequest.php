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

            $note = $this->route('behavioralNote');
            if (!$note instanceof \App\Models\BehavioralNote) {
                return;
            }

            // اتساق الطالب/الحلقة كما في الإنشاء: الزوج الفعّال بعد التعديل
            // (المُرسل أو القائم) يجب أن يطابق عضوية الطالب في حلقته — كان
            // التعديل يفحص نطاق كل معرف وحده فتمر أزواج غير متسقة.
            if ($this->filled('student_id') || $this->filled('circle_id')) {
                $effectiveStudentId = $this->filled('student_id') ? (int) $this->input('student_id') : (int) $note->student_id;
                $effectiveCircleId  = $this->filled('circle_id') ? (int) $this->input('circle_id') : (int) $note->circle_id;
                $effectiveStudent   = Student::withoutGlobalScope(CenterScope::class)->find($effectiveStudentId);
                if ($effectiveStudent && (int) $effectiveStudent->circle_id !== $effectiveCircleId) {
                    $v->errors()->add('student_id', 'الطالب المختار ليس مسجلًا في الحلقة المختارة.');
                }
            }

            // قفل الواجهة مفروض خادميًا: فور خروج الملاحظة من «قيد الانتظار»
            // تُقفل حقول الواقعة في النموذج (readonly/معطّلة)، فلا تُقبل
            // إعادة كتابتها بحمولة مصوغة بعد تسجيل إجراء المشرف.
            if ($note->status !== \App\Models\BehavioralNote::STATUS_PENDING) {
                foreach (['student_id', 'circle_id', 'teacher_id'] as $field) {
                    if ($this->filled($field) && (int) $this->input($field) !== (int) $note->{$field}) {
                        $v->errors()->add($field, 'بيانات الواقعة مقفلة بعد بدء معالجة الملاحظة.');
                    }
                }
                if ($this->filled('incident_at') && $note->incident_at
                    && \Carbon\Carbon::parse($this->input('incident_at'))->format('Y-m-d H:i') !== $note->incident_at->format('Y-m-d H:i')) {
                    $v->errors()->add('incident_at', 'بيانات الواقعة مقفلة بعد بدء معالجة الملاحظة.');
                }
                if ($this->filled('behavior') && $this->input('behavior') !== $note->behavior) {
                    $v->errors()->add('behavior', 'بيانات الواقعة مقفلة بعد بدء معالجة الملاحظة.');
                }
            }

            // تاريخ المتابعة من حقول إجراء المشرف (يظهر في النموذج بعد تسجيل
            // الإجراء فقط): لا يُغيَّر عبر التعديل العادي ما دامت الملاحظة
            // قيد الانتظار أو قيد التحقيق.
            if (in_array($note->status, [\App\Models\BehavioralNote::STATUS_PENDING, \App\Models\BehavioralNote::STATUS_UNDER_REVIEW], true)
                && $this->filled('follow_up_at')
                && $this->input('follow_up_at') !== optional($note->follow_up_at)->format('Y-m-d')) {
                $v->errors()->add('follow_up_at', 'تاريخ المتابعة يُحدَّد بعد تسجيل إجراء المشرف.');
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
