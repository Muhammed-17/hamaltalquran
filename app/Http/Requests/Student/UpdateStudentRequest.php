<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Step 1
            // ملاحظة: سابت supervisor_id/join_date اختياريين هنا (بدل required)
            // عشان لا نمنع تعديل بيانات طلاب قدامى مسجّلين قبل ما الحقول دي تبقى إلزامية.
            'supervisor_id'   => 'integer|exists:teachers,id',
            'join_date'       => 'date',
            'applicant'       => 'string|max:50',

            // Step 2
            'student_code' => [
                'nullable',
                'digits:14',
                Rule::unique('students', 'student_code')->ignore($this->route('id') ?? $this->route('student')),
            ],
            'name'                      => 'required|string|max:255',
            'gender'                    => 'required|in:ذكر,أنثى',
            'date_of_birth'             => 'nullable|date',
            'address'                   => 'required|string',
            'center_id'                 => 'nullable|integer|exists:centers,id',
            'whatsapp_number'           => 'nullable|string|max:20',
            'whatsapp_owner'            => 'nullable|string|max:50',
            'second_phone'              => 'nullable|string|max:20',
            'additional_contact_owner'  => 'nullable|string|max:50',

            // ✅ FIX: نفس إصلاح الـ Store — required_if + email format
            'parent_email' => 'required_if:guardian_id,new|nullable|email|max:255',
            'password'     => 'nullable|string|min:6',

            'guardian_id' => [
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') return;
                    if (in_array((string) $value, ['new', 'other', 'none'])) return;
                    if (!\App\Models\User::where('id', $value)->exists()) {
                        $fail('ولي الأمر المختار غير موجود.');
                    }
                },
            ],
            'guardian_name' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if ($this->guardian_id === 'new' && empty($value)) {
                        $fail('اسم ولي الأمر مطلوب عند إضافة حساب جديد.');
                    }
                },
            ],

            // Step 3
            'educational_stage' => 'nullable|string|max:100',
            'education_type'    => 'nullable|string|max:100',
            'school_grade'      => 'nullable|string|max:100',
            'previous_school'   => 'nullable|string|max:255',

            // Step 4
            'health_status'         => 'nullable|string|max:100',
            'learning_difficulties' => 'nullable|string|max:255',
            'personal_traits'       => 'nullable|string|max:255',
            'hobbies'                => 'nullable|array',
            'hobbies.*'              => 'string',
            'student_exit_status'   => 'nullable|string|max:100',

            // Step 5
            'reading'            => 'nullable|string|max:50',
            'center_entry_level' => 'nullable|in:construction,mastery,creativity',

            // Step 6 - Construction
            // ✅ FIX: أُضيفت required_if بدل ما تكون nullable مطلقًا — كانت بتسمح
            // بحفظ خطة بناء بدون حلقة أو نظام دراسة حتى لو center_entry_level = construction
            'circle_id'              => 'required_if:center_entry_level,construction|nullable|integer|exists:circles,id',
            'current_surah_id'       => 'nullable|integer|exists:surahs,id',
            'study_system'           => 'required_if:center_entry_level,construction|nullable|in:group,individual',
            'new_memorization_plan'  => 'required_if:study_system,individual|nullable|string',
            'revision_plan'          => 'required_if:study_system,individual|nullable|string|max:100',
            'placement_evaluation'  => 'nullable|string',
            'old_memorization_plan'  => 'required_if:study_system,individual|nullable|string',

            // Step 7 - Mastery
            // ✅ FIX: أُضيفت required_if بدل nullable مطلقًا، لنفس سبب الحلقة أعلاه
            'previous_memorization_side' => 'required_if:center_entry_level,mastery|nullable|string|max:255',
            'previous_khatamat_count'    => 'required_if:center_entry_level,mastery|nullable|string',
            'current_review_amount'      => 'required_if:center_entry_level,mastery|nullable|string|max:255',
            'self_evaluation'            => 'required_if:center_entry_level,mastery|nullable|integer|min:1|max:10',
            'tajweed_matn'                => 'required_if:center_entry_level,mastery|nullable|string|max:100',
            'memorized_texts'            => 'nullable|string',
            'desired_path'                => 'required_if:center_entry_level,mastery|nullable|string|max:255',
            'preferred_time_mastery'      => 'required_if:center_entry_level,mastery|nullable|string|max:100',
            'preferred_time_creativity'   => 'required_if:center_entry_level,creativity|nullable|string|max:100',
            'teacher_name'                => 'nullable|string|max:255',
            'itqan_details'               => 'nullable|string',

            // Step 8 - Creativity
            'previous_licenses_and_chains' => 'required_if:center_entry_level,creativity|nullable|string',
            'desired_narration_and_path'   => 'required_if:center_entry_level,creativity|nullable|string|max:255',
            'supervisor_name'              => 'nullable|string|max:255',
            'ibda_details'                 => 'nullable|string',

            // Step 9
            'notes'              => 'nullable|string',
            // ✅ FIX: الحقول الأربعة دي بقت مطلوبة بناءً على طلبك
            'status'             => 'required|string|max:50',
            'decision'           => 'required|string|max:50',
            'subscription_fees'  => 'required|string|max:50',
            'received_tools'     => 'required|string|max:100',

            // ✅ حقول Modal تأكيد إرسال رسالة الواتساب عند تغيير الحلقة (مش أعمدة في جدول students)
            'send_whatsapp_message' => 'nullable|in:0,1',
            'whatsapp_message_text' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'supervisor_id.integer' => 'المشرف المختار غير صالح',
            'name.required'      => 'الاسم مطلوب',
            'gender.required'    => 'الجنس مطلوب',
            'gender.in'          => 'الجنس يجب أن يكون ذكر أو أنثى',
            'address.required'   => 'العنوان مطلوب',
            'center_id.exists'   => 'المركز المختار غير موجود',
            'student_code.digits' => 'الرقم القومي يجب أن يتكون من 14 رقمًا بالضبط',
            'student_code.unique' => 'الرقم القومي مستخدم مسبقاً لطالب آخر',
            'center_entry_level.in' => 'مستوى الالتحاق غير صحيح',
            'applicant.required'       => 'مقدم طلب التسجيل مطلوب',
            'parent_email.required_if' => 'البريد الإلكتروني مطلوب عند إضافة ولي أمر جديد',
            'parent_email.email'       => 'صيغة البريد الإلكتروني غير صحيحة',
            'circle_id.required_if'    => 'الحلقة مطلوبة لمستوى البناء',
            'study_system.required_if' => 'نظام الدراسة مطلوب لمستوى البناء',
            'new_memorization_plan.required_if' => 'خطة الحفظ الجديد مطلوبة للنظام الفردي',
            'revision_plan.required_if'         => 'خطة المراجعة مطلوبة للنظام الفردي',
            'old_memorization_plan.required_if' => 'خطة الحفظ القديم مطلوبة للنظام الفردي',
            'previous_memorization_side.required_if' => 'جهة الحفظ السابقة مطلوبة لمستوى الإتقان',
            'previous_khatamat_count.required_if'    => 'عدد الختمات السابقة مطلوب لمستوى الإتقان',
            'current_review_amount.required_if'      => 'مقدار المراجعة الحالي مطلوب لمستوى الإتقان',
            'self_evaluation.required_if'            => 'تقييم مستوى الحفظ مطلوب لمستوى الإتقان',
            'tajweed_matn.required_if'               => 'متن التجويد مطلوب لمستوى الإتقان',
            'desired_path.required_if'               => 'المسار المرغوب مطلوب لمستوى الإتقان',
            'preferred_time_mastery.required_if'     => 'الوقت المناسب للمجلس مطلوب',
            'preferred_time_creativity.required_if'  => 'الوقت المناسب للمجلس مطلوب',
            'previous_licenses_and_chains.required_if' => 'الإجازات والأسانيد مطلوبة لمستوى الإبداع',
            'desired_narration_and_path.required_if'   => 'الرواية المراد دراستها مطلوبة لمستوى الإبداع',
            'status.required'            => 'حالة الطالب مطلوبة',
            'decision.required'          => 'قرار الإدارة مطلوب',
            'subscription_fees.required' => 'رسوم حجز المقعد مطلوبة',
            'received_tools.required'    => 'يجب تحديد الأدوات والكتب المستلمة',
            'send_whatsapp_message.in'   => 'قيمة إرسال رسالة الواتساب غير صحيحة',
            'whatsapp_message_text.max'  => 'نص رسالة الواتساب طويل جدًا (الحد الأقصى 1000 حرف)',
        ];
    }
}
