<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Step 1
            'supervisor_id'   => 'required|integer|exists:teachers,id',
            'join_date'       => 'required|date',
            // ✅ FIX: بقت مطلوبة بناءً على طلبك
            'applicant'       => 'required|string|max:50',

            // Step 2
            'student_code'                    => 'nullable|digits:14|unique:students,student_code',
            'name'                            => 'required|string|max:255',
            'gender'                          => 'required|in:ذكر,أنثى',
            'date_of_birth'                   => 'nullable|date',
            'address'                         => 'required|string',
            'center_id'                       => 'required|integer|exists:centers,id',
            'whatsapp_number'                 => 'nullable|string|max:20',
            'whatsapp_owner'                  => 'nullable|string|max:50',
            'second_phone'                    => 'nullable|string|max:20',
            'additional_contact_owner'        => 'nullable|string|max:50',

            // ✅ FIX: كانت string فقط بدون تنسيق بريد صحيح، وبدون required_if رغم وجود رسالة له
            'parent_email' => 'required_if:guardian_id,new|nullable|email|max:255',
            // ✅ FIX: بدون required_if رغم وجود رسالة له — لكن تُركت اختيارية عمدًا
            // لأن الكنترولر بيولّد باسورد تلقائي (Str::random) لو اتسابت فاضية
            'password' => 'nullable|string|min:6',

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
            'educational_stage' => 'required|string|max:100',
            'education_type'    => 'required|string|max:100',
            'school_grade'      => 'required|string|max:100',
            'previous_school'   => 'required|string|max:255',

            // Step 4
            'health_status'         => 'required|string|max:100',
            'learning_difficulties' => 'required|string|max:255',
            'personal_traits'       => 'required|string|max:255',
            'hobbies'                => 'nullable|array',
            'hobbies.*'              => 'string',
            'student_exit_status'   => 'required|string|max:100',

            // Step 5
            'reading'            => 'required|string|max:50',
            'center_entry_level' => 'required|in:construction,mastery,creativity',

            // Step 6 - Construction
            'placement_evaluation'  => 'nullable|string',
            'current_surah_id'      => 'nullable|integer|exists:surahs,id',
            'new_memorization_plan' => 'required_if:study_system,individual|nullable|string',
            'revision_plan'         => 'required_if:study_system,individual|nullable|string|max:100',
            'old_memorization_plan' => 'required_if:study_system,individual|nullable|string',
            'study_system'          => 'required_if:center_entry_level,construction|nullable|in:group,individual',
            'circle_id'             => 'required_if:center_entry_level,construction|nullable|integer|exists:circles,id',

            // Step 7 - Mastery
            'previous_memorization_side' => 'required_if:center_entry_level,mastery|nullable|string|max:255',
            'previous_khatamat_count'    => 'required_if:center_entry_level,mastery|nullable|string',
            'current_review_amount'      => 'required_if:center_entry_level,mastery|nullable|string|max:255',
            // ✅ FIX: الفورم بيحطها كـ * (مطلوبة) لمستوى الإتقان لكن كانت nullable فقط
            'self_evaluation'            => 'required_if:center_entry_level,mastery|nullable|integer|min:1|max:10',
            'tajweed_matn'                => 'required_if:center_entry_level,mastery|nullable|string|max:100',
            'desired_path'                => 'required_if:center_entry_level,mastery|nullable|string|max:255',
            // ✅ FIX: تم فصلها لحقلين مستقلين لتفادي تعارض الاسم preferred_time بين مستوى الإتقان والإبداع
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

            // ✅ حقول Modal تأكيد إرسال رسالة الواتساب (مش أعمدة في جدول students، بتتقرأ في الكنترولر فقط)
            'send_whatsapp_message' => 'nullable|in:0,1',
            'whatsapp_message_text' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'supervisor_id.integer'     => 'المشرف مطلوب',
            'supervisor_id.required'    => 'المشرف مطلوب',
            'join_date.required'        => 'تاريخ التسجيل مطلوب',
            'applicant.required'        => 'مقدم طلب التسجيل مطلوب',
            'student_code.digits'       => 'الرقم القومي يجب أن يتكون من 14 رقمًا بالضبط',
            'student_code.unique'       => 'الرقم القومي مستخدم مسبقاً لطالب آخر',
            'name.required'             => 'الاسم مطلوب',
            'gender.required'           => 'الجنس مطلوب',
            'gender.in'                 => 'الجنس يجب أن يكون ذكر أو أنثى',
            'address.required'          => 'العنوان مطلوب',
            'center_id.required'        => 'المركز مطلوب',
            'center_id.exists'          => 'المركز المختار غير موجود',
            'parent_email.required_if'  => 'البريد الإلكتروني مطلوب عند إضافة ولي أمر جديد',
            'parent_email.email'        => 'صيغة البريد الإلكتروني غير صحيحة',
            'educational_stage.required' => 'المرحلة الدراسية مطلوبة',
            'education_type.required'   => 'نوع التعليم مطلوب',
            'school_grade.required'     => 'الصف الدراسي مطلوب',
            'previous_school.required'  => 'المؤسسة التعليمية مطلوبة',
            'health_status.required'    => 'الحالة الصحية مطلوبة',
            'learning_difficulties.required' => 'صعوبات التعلم مطلوبة',
            'personal_traits.required'  => 'السمات الشخصية مطلوبة',
            'student_exit_status.required' => 'حالة خروج الطالب مطلوبة',
            'reading.required'          => 'مستوى القراءة مطلوب',
            'center_entry_level.required' => 'مستوى الالتحاق مطلوب',
            'center_entry_level.in'     => 'مستوى الالتحاق غير صحيح',
            'study_system.required_if'  => 'نظام الدراسة مطلوب لمستوى البناء',
            'circle_id.required_if'     => 'الحلقة مطلوبة لمستوى البناء',
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
        ];
    }
}
