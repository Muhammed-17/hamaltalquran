<?php

namespace App\Http\Requests\WeeklyFollowup;

use App\Models\Scopes\CenterScope;
use App\Models\StudentWeeklyFollowup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreIndividualWeeklyFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * منع تكرار متابعة فردية لنفس الطالب في نفس الأسبوع برسالة واضحة،
     * بدل أن يصل الطلب إلى قيد قاعدة البيانات ويظهر خطأ SQL خام للمستخدم.
     * (الفورم يستبعد هؤلاء الطلاب للأسبوع الافتراضي فقط، وتغيير الأسبوع
     * داخل الفورم يعيد فتح باب التكرار — فهذا هو الحارس الفعلي.)
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || !$this->filled('student_id') || !$this->filled('week_start')) {
                return;
            }

            $exists = StudentWeeklyFollowup::withoutGlobalScope(CenterScope::class)
                ->where('student_id', $this->input('student_id'))
                ->whereDate('week_start', $this->input('week_start'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('student_id', 'هذا الطالب لديه متابعة مسجلة لهذا الأسبوع بالفعل.');
            }
        });
    }

    public function rules(): array
    {
        return [
            // Basic info
            'circle_id'        => ['nullable', 'integer', 'exists:circles,id'],
            'student_id'       => ['required', 'integer', 'exists:students,id'],
            'teacher_id'       => ['required', 'integer', 'exists:teachers,id'],
            'week_start'       => ['required', 'date', 'date_format:Y-m-d'],
            'week_end'         => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:week_start'],
            'study_days'       => ['nullable', 'array'],
            'study_days.*'     => ['string', 'in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday'],
            'notes'            => ['nullable', 'string'],

            // New Memorization (flat fields)
            'memorization_from_surah_id'       => ['nullable', 'integer', 'exists:surahs,id'],
            'memorization_from_ayah'           => ['nullable', 'integer', 'min:1'],
            'memorization_to_surah_id'         => ['nullable', 'integer', 'exists:surahs,id'],
            'memorization_to_ayah'             => ['nullable', 'integer', 'min:1'],
            'memorization_plan_comparison'     => ['nullable', 'string', 'max:255'],
            'memorization_progress_difference' => ['nullable', 'string', 'max:255'],
            'memorization_notes'               => ['nullable', 'string'],
            'new_memorization_level'           => ['nullable', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],

            // Revision (flat fields)
            'revision_from_surah_id'       => ['nullable', 'integer', 'exists:surahs,id'],
            'revision_from_ayah'           => ['nullable', 'integer', 'min:1'],
            'revision_to_surah_id'         => ['nullable', 'integer', 'exists:surahs,id'],
            'revision_to_ayah'             => ['nullable', 'integer', 'min:1'],
            'revision_plan_comparison'     => ['nullable', 'string', 'max:255'],
            'revision_progress_difference' => ['nullable', 'string', 'max:255'],
            'revision_notes'               => ['nullable', 'string'],
            'revision_level'               => ['nullable', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],

            // Old Memorization (flat fields)
            'old_revision_from_surah_id'       => ['nullable', 'integer', 'exists:surahs,id'],
            'old_revision_from_ayah'           => ['nullable', 'integer', 'min:1'],
            'old_revision_to_surah_id'         => ['nullable', 'integer', 'exists:surahs,id'],
            'old_revision_to_ayah'             => ['nullable', 'integer', 'min:1'],
            'old_revision_plan_comparison'     => ['nullable', 'string', 'max:255'],
            'old_revision_progress_difference' => ['nullable', 'string', 'max:255'],
            'old_revision_notes'               => ['nullable', 'string'],
            'old_memorization_level'           => ['nullable', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],

            // Assessments
            'discipline_level'              => ['nullable', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'discipline_achievement'        => ['nullable', 'string', 'max:500'],
            'tajweed_level'                 => ['nullable', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'tajweed_achievement'           => ['nullable', 'string', 'max:500'],
            'foundation_level_level'        => ['nullable', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'foundation_level_achievement'  => ['nullable', 'string', 'max:500'],

            // Educational Lesson
            'educational_lesson_id'         => ['nullable', 'integer', 'exists:educational_lessons,id'],
            'educational_lesson_level'      => ['nullable', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'educational_lesson_notes'      => ['nullable', 'string', 'max:500'],

            // Activities
            'activities'                    => ['nullable', 'array'],
            'activities.*.activity_type'    => ['required_with:activities', 'string', 'max:100'],
            'activities.*.activity_name'    => ['required_with:activities', 'string', 'max:255'],
            'activities.*.activity_date'    => ['required_with:activities', 'date'],
            'activities.*.notes'            => ['nullable', 'string', 'max:500'],
            'activities.*._deleted'         => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // Basic info
            'circle_id.integer' => 'معرف الحلقة يجب أن يكون رقمًا صحيحًا.',
            'circle_id.exists'  => 'الحلقة المختارة غير موجودة.',

            'student_id.required' => 'الطالب مطلوب.',
            'student_id.integer'  => 'معرف الطالب يجب أن يكون رقمًا صحيحًا.',
            'student_id.exists'   => 'الطالب المختار غير موجود.',

            'teacher_id.required' => 'المعلم مطلوب.',
            'teacher_id.integer'  => 'معرف المعلم يجب أن يكون رقمًا صحيحًا.',
            'teacher_id.exists'   => 'المعلم المختار غير موجود.',

            'week_start.required'    => 'تاريخ بداية الأسبوع مطلوب.',
            'week_start.date'        => 'تاريخ بداية الأسبوع يجب أن يكون تاريخًا صحيحًا.',
            'week_start.date_format' => 'صيغة تاريخ بداية الأسبوع يجب أن تكون Y-m-d.',

            'week_end.required'       => 'تاريخ نهاية الأسبوع مطلوب.',
            'week_end.date'           => 'تاريخ نهاية الأسبوع يجب أن يكون تاريخًا صحيحًا.',
            'week_end.date_format'    => 'صيغة تاريخ نهاية الأسبوع يجب أن تكون Y-m-d.',
            'week_end.after_or_equal' => 'تاريخ نهاية الأسبوع يجب أن يكون مساويًا أو بعد تاريخ بداية الأسبوع.',

            'study_days.array'    => 'أيام الدراسة يجب أن تكون قائمة.',
            'study_days.*.string' => 'اليوم يجب أن يكون نصًا.',
            'study_days.*.in'     => 'اليوم المحدد غير صحيح.',

            'notes.string' => 'الملاحظات يجب أن تكون نصًا.',

            // New Memorization
            'memorization_from_surah_id.integer' => 'سورة البداية في الحفظ الجديد يجب أن تكون رقمًا صحيحًا.',
            'memorization_from_surah_id.exists'  => 'سورة البداية في الحفظ الجديد غير موجودة.',
            'memorization_from_ayah.integer'     => 'رقم آية البداية في الحفظ الجديد يجب أن يكون رقمًا صحيحًا.',
            'memorization_from_ayah.min'         => 'رقم آية البداية في الحفظ الجديد يجب ألا يقل عن :min.',

            'memorization_to_surah_id.integer' => 'سورة النهاية في الحفظ الجديد يجب أن تكون رقمًا صحيحًا.',
            'memorization_to_surah_id.exists'  => 'سورة النهاية في الحفظ الجديد غير موجودة.',
            'memorization_to_ayah.integer'     => 'رقم آية النهاية في الحفظ الجديد يجب أن يكون رقمًا صحيحًا.',
            'memorization_to_ayah.min'         => 'رقم آية النهاية في الحفظ الجديد يجب ألا يقل عن :min.',

            'memorization_plan_comparison.string'     => 'مقارنة الخطة للحفظ الجديد يجب أن تكون نصًا.',
            'memorization_plan_comparison.max'        => 'مقارنة الخطة للحفظ الجديد يجب ألا تتجاوز :max حرفًا.',
            'memorization_progress_difference.string' => 'فرق التقدم للحفظ الجديد يجب أن يكون نصًا.',
            'memorization_progress_difference.max'    => 'فرق التقدم للحفظ الجديد يجب ألا يتجاوز :max حرفًا.',
            'memorization_notes.string'               => 'ملاحظات الحفظ الجديد يجب أن تكون نصًا.',
            'new_memorization_level.string'           => 'مستوى الحفظ الجديد يجب أن يكون نصًا.',
            'new_memorization_level.in'               => 'القيمة المختارة لمستوى الحفظ الجديد غير صحيحة.',

            // Revision
            'revision_from_surah_id.integer' => 'سورة البداية في المراجعة يجب أن تكون رقمًا صحيحًا.',
            'revision_from_surah_id.exists'  => 'سورة البداية في المراجعة غير موجودة.',
            'revision_from_ayah.integer'     => 'رقم آية البداية في المراجعة يجب أن يكون رقمًا صحيحًا.',
            'revision_from_ayah.min'         => 'رقم آية البداية في المراجعة يجب ألا يقل عن :min.',

            'revision_to_surah_id.integer' => 'سورة النهاية في المراجعة يجب أن تكون رقمًا صحيحًا.',
            'revision_to_surah_id.exists'  => 'سورة النهاية في المراجعة غير موجودة.',
            'revision_to_ayah.integer'     => 'رقم آية النهاية في المراجعة يجب أن يكون رقمًا صحيحًا.',
            'revision_to_ayah.min'         => 'رقم آية النهاية في المراجعة يجب ألا يقل عن :min.',

            'revision_plan_comparison.string'     => 'مقارنة الخطة للمراجعة يجب أن تكون نصًا.',
            'revision_plan_comparison.max'        => 'مقارنة الخطة للمراجعة يجب ألا تتجاوز :max حرفًا.',
            'revision_progress_difference.string' => 'فرق التقدم للمراجعة يجب أن يكون نصًا.',
            'revision_progress_difference.max'    => 'فرق التقدم للمراجعة يجب ألا يتجاوز :max حرفًا.',
            'revision_notes.string'               => 'ملاحظات المراجعة يجب أن تكون نصًا.',
            'revision_level.string'               => 'مستوى المراجعة يجب أن يكون نصًا.',
            'revision_level.in'                   => 'القيمة المختارة لمستوى المراجعة غير صحيحة.',

            // Old Memorization
            'old_revision_from_surah_id.integer' => 'سورة البداية في الحفظ القديم يجب أن تكون رقمًا صحيحًا.',
            'old_revision_from_surah_id.exists'  => 'سورة البداية في الحفظ القديم غير موجودة.',
            'old_revision_from_ayah.integer'     => 'رقم آية البداية في الحفظ القديم يجب أن يكون رقمًا صحيحًا.',
            'old_revision_from_ayah.min'         => 'رقم آية البداية في الحفظ القديم يجب ألا يقل عن :min.',

            'old_revision_to_surah_id.integer' => 'سورة النهاية في الحفظ القديم يجب أن تكون رقمًا صحيحًا.',
            'old_revision_to_surah_id.exists'  => 'سورة النهاية في الحفظ القديم غير موجودة.',
            'old_revision_to_ayah.integer'     => 'رقم آية النهاية في الحفظ القديم يجب أن يكون رقمًا صحيحًا.',
            'old_revision_to_ayah.min'         => 'رقم آية النهاية في الحفظ القديم يجب ألا يقل عن :min.',

            'old_revision_plan_comparison.string'     => 'مقارنة الخطة للحفظ القديم يجب أن تكون نصًا.',
            'old_revision_plan_comparison.max'        => 'مقارنة الخطة للحفظ القديم يجب ألا تتجاوز :max حرفًا.',
            'old_revision_progress_difference.string' => 'فرق التقدم للحفظ القديم يجب أن يكون نصًا.',
            'old_revision_progress_difference.max'    => 'فرق التقدم للحفظ القديم يجب ألا يتجاوز :max حرفًا.',
            'old_revision_notes.string'               => 'ملاحظات الحفظ القديم يجب أن تكون نصًا.',
            'old_memorization_level.string'           => 'مستوى الحفظ القديم يجب أن يكون نصًا.',
            'old_memorization_level.in'               => 'القيمة المختارة لمستوى الحفظ القديم غير صحيحة.',

            // Assessments
            'discipline_level.string'              => 'مستوى الانضباط يجب أن يكون نصًا.',
            'discipline_level.in'                  => 'القيمة المختارة لمستوى الانضباط غير صحيحة.',
            'discipline_achievement.string'        => 'إنجاز السلوك والانضباط يجب أن يكون نصًا.',
            'discipline_achievement.max'           => 'إنجاز السلوك والانضباط يجب ألا يتجاوز :max حرفًا.',

            'tajweed_level.string'                 => 'مستوى التجويد يجب أن يكون نصًا.',
            'tajweed_level.in'                     => 'القيمة المختارة لمستوى التجويد غير صحيحة.',
            'tajweed_achievement.string'           => 'إنجاز التجويد يجب أن يكون نصًا.',
            'tajweed_achievement.max'              => 'إنجاز التجويد يجب ألا يتجاوز :max حرفًا.',

            'foundation_level_level.string'        => 'المستوى التأسيسي يجب أن يكون نصًا.',
            'foundation_level_level.in'            => 'القيمة المختارة للمستوى التأسيسي غير صحيحة.',
            'foundation_level_achievement.string'  => 'إنجاز المستوى التأسيسي يجب أن يكون نصًا.',
            'foundation_level_achievement.max'     => 'إنجاز المستوى التأسيسي يجب ألا يتجاوز :max حرفًا.',

            // Educational Lesson
            'educational_lesson_id.integer'     => 'معرف الدرس التربوي يجب أن يكون رقمًا صحيحًا.',
            'educational_lesson_id.exists'      => 'الدرس التربوي المختار غير موجود.',
            'educational_lesson_level.string'  => 'مستوى الدرس التربوي يجب أن يكون نصًا.',
            'educational_lesson_level.in'      => 'القيمة المختارة لمستوى الدرس التربوي غير صحيحة.',
            'educational_lesson_notes.string'  => 'ملاحظات الدرس التربوي يجب أن تكون نصًا.',
            'educational_lesson_notes.max'     => 'ملاحظات الدرس التربوي يجب ألا تتجاوز :max حرفًا.',

            // Activities
            'activities.array'                            => 'الأنشطة يجب أن تكون قائمة.',
            'activities.*.activity_type.required_with'    => 'نوع النشاط مطلوب عند إضافة نشاط.',
            'activities.*.activity_type.string'           => 'نوع النشاط يجب أن يكون نصًا.',
            'activities.*.activity_type.max'              => 'نوع النشاط يجب ألا يتجاوز :max حرفًا.',
            'activities.*.activity_name.required_with'    => 'اسم النشاط مطلوب عند إضافة نشاط.',
            'activities.*.activity_name.string'           => 'اسم النشاط يجب أن يكون نصًا.',
            'activities.*.activity_name.max'              => 'اسم النشاط يجب ألا يتجاوز :max حرفًا.',
            'activities.*.activity_date.required_with'    => 'تاريخ النشاط مطلوب عند إضافة نشاط.',
            'activities.*.activity_date.date'             => 'تاريخ النشاط يجب أن يكون تاريخًا صحيحًا.',
            'activities.*.notes.string'                   => 'ملاحظات النشاط يجب أن تكون نصًا.',
            'activities.*.notes.max'                      => 'ملاحظات النشاط يجب ألا تتجاوز :max حرفًا.',
        ];
    }
}
