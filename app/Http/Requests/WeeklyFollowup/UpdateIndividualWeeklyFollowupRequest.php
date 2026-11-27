<?php

namespace App\Http\Requests\WeeklyFollowup;

use App\Models\Scopes\CenterScope;
use App\Models\StudentWeeklyFollowup;
use App\Services\UserAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateIndividualWeeklyFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * منع نقل المتابعة إلى طالب/أسبوع يصطدم بمتابعة أخرى موجودة،
     * برسالة واضحة بدل خطأ قيد قاعدة البيانات الخام.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || !$this->filled('student_id') || !$this->filled('week_start')) {
                return;
            }

            $current = $this->route('studentWeeklyFollowup');
            $currentId = $current instanceof StudentWeeklyFollowup ? $current->id : $current;

            $exists = StudentWeeklyFollowup::withoutGlobalScope(CenterScope::class)
                ->where('student_id', $this->input('student_id'))
                ->whereDate('week_start', $this->input('week_start'))
                ->when($currentId, fn($q) => $q->where('id', '!=', $currentId))
                ->exists();

            if ($exists) {
                $validator->errors()->add('student_id', 'هذا الطالب لديه متابعة مسجلة لهذا الأسبوع بالفعل.');
            }
        });
    }

    public function rules(): array
    {
        // المعلم المسمّى يجب أن يكون ضمن معلمي الفاعل المتاحين كما في طلب
        // الإنشاء تمامًا (والمعلم العادي لا يسمّي إلا نفسه) — exists وحده
        // كان يسمح بنقل المتابعة لمعلم من مركز آخر عبر التعديل.
        $user   = $this->user();
        $access = app(UserAccessService::class);
        if (!$user) {
            $accessibleTeacherIds = null; // بلا فاعل: قاعدة الوجود فقط
        } elseif ($user->hasRole(['admin', 'general_manager', 'manager', 'supervisor'])) {
            $accessibleTeacherIds = $access->accessibleTeachers($user)->pluck('id')->toArray();
        } else {
            $currentTeacher = $access->teacher($user);
            $accessibleTeacherIds = $currentTeacher ? [$currentTeacher->id] : [];
        }

        return [
            // Basic info
            'circle_id'        => ['nullable', 'integer', 'exists:circles,id'],
            'student_id'       => ['required', 'integer', 'exists:students,id'],
            'teacher_id'       => $accessibleTeacherIds === null
                ? ['required', 'integer', 'exists:teachers,id']
                : ['required', 'integer', Rule::in($accessibleTeacherIds)],
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
            // Basic Info
            'student_id.required'        => 'حقل الطالب مطلوب.',
            'student_id.exists'          => 'الطالب المختار غير موجود.',
            'teacher_id.required'        => 'حقل المعلم مطلوب.',
            'teacher_id.exists'          => 'المعلم المختار غير موجود.',
            'teacher_id.in'              => 'المعلم المختار خارج نطاق صلاحياتك.',
            'circle_id.exists'           => 'الحلقة المختارة غير موجودة.',

            'week_start.required'        => 'حقل بداية الأسبوع مطلوب.',
            'week_start.date'            => 'حقل بداية الأسبوع يجب أن يكون تاريخًا صحيحًا.',
            'week_start.date_format'     => 'صيغة تاريخ بداية الأسبوع غير صحيحة.',

            'week_end.required'          => 'حقل نهاية الأسبوع مطلوب.',
            'week_end.date'              => 'حقل نهاية الأسبوع يجب أن يكون تاريخًا صحيحًا.',
            'week_end.date_format'       => 'صيغة تاريخ نهاية الأسبوع غير صحيحة.',
            'week_end.after_or_equal'    => 'تاريخ نهاية الأسبوع يجب أن يكون بعد أو مساويًا لتاريخ بداية الأسبوع.',

            'study_days.array'           => 'حقل أيام الدراسة يجب أن يكون قائمة.',
            'study_days.*.in'            => 'إحدى القيم المختارة في أيام الدراسة غير صحيحة.',

            // Surah & Ayah Validation Checks
            'memorization_from_surah_id.exists' => 'سورة البداية المختارة للحفظ الجديد غير موجودة.',
            'memorization_to_surah_id.exists'   => 'سورة النهاية المختارة للحفظ الجديد غير موجودة.',
            'memorization_from_ayah.min'        => 'رقم آية البداية للحفظ الجديد يجب أن يكون 1 أو أكثر.',
            'memorization_to_ayah.min'          => 'رقم آية النهاية للحفظ الجديد يجب أن يكون 1 أو أكثر.',

            'revision_from_surah_id.exists'     => 'سورة البداية المختارة للمراجعة غير موجودة.',
            'revision_to_surah_id.exists'       => 'سورة النهاية المختارة للمراجعة غير موجودة.',
            'revision_from_ayah.min'            => 'رقم آية البداية للمراجعة يجب أن يكون 1 أو أكثر.',
            'revision_to_ayah.min'              => 'رقم آية النهاية للمراجعة يجب أن يكون 1 أو أكثر.',

            'old_revision_from_surah_id.exists' => 'سورة البداية المختارة للحفظ القديم غير موجودة.',
            'old_revision_to_surah_id.exists'   => 'سورة النهاية المختارة للحفظ القديم غير موجودة.',
            'old_revision_from_ayah.min'        => 'رقم آية البداية للحفظ القديم يجب أن يكون 1 أو أكثر.',
            'old_revision_to_ayah.min'          => 'رقم آية النهاية للحفظ القديم يجب أن يكون 1 أو أكثر.',

            // Levels Validation
            'new_memorization_level.in'  => 'مستوى الحفظ الجديد يجب أن يكون أحد الخيارات المتاحة.',
            'revision_level.in'          => 'مستوى المراجعة يجب أن يكون أحد الخيارات المتاحة.',
            'old_memorization_level.in'  => 'مستوى الحفظ القديم يجب أن يكون أحد الخيارات المتاحة.',
            'discipline_level.in'        => 'مستوى الانضباط يجب أن يكون أحد الخيارات المتاحة.',
            'tajweed_level.in'           => 'مستوى التجويد يجب أن يكون أحد الخيارات المتاحة.',
            'foundation_level_level.in'  => 'المستوى التأسيسي يجب أن يكون أحد الخيارات المتاحة.',
            'educational_lesson_level.in'=> 'مستوى الدرس التربوي يجب أن يكون أحد الخيارات المتاحة.',

            // Educational Lesson & Activities
            'educational_lesson_id.exists'               => 'الدرس التربوي المختار غير موجود.',
            'activities.*.activity_type.required_with'  => 'نوع النشاط مطلوب عند إضافة نشاط.',
            'activities.*.activity_name.required_with'  => 'اسم النشاط مطلوب عند إضافة نشاط.',
            'activities.*.activity_date.required_with'  => 'تاريخ النشاط مطلوب عند إضافة نشاط.',
            'activities.*.activity_date.date'           => 'تاريخ النشاط يجب أن يكون تاريخًا صحيحًا.',

            // Fallback Defaults
            'required'       => 'هذا الحقل مطلوب.',
            'integer'        => 'هذا الحقل يجب أن يكون رقمًا صحيحًا.',
            'string'         => 'هذا الحقل يجب أن يكون نصًا.',
            'array'          => 'هذا الحقل يجب أن يكون قائمة.',
            'min'            => 'هذا الحقل يجب ألا يقل عن :min.',
            'max'            => 'هذا الحقل يجب ألا يزيد عن :max حرفًا.',
            'date'           => 'هذا الحقل يجب أن يكون تاريخًا صحيحًا.',
            'date_format'    => 'صيغة هذا الحقل غير صحيحة.',
            'after_or_equal' => 'هذا الحقل يجب أن يكون تاريخًا بعد أو مساويًا للمحدد.',
            'exists'         => 'القيمة المختارة غير موجودة.',
            'in'             => 'القيمة المختارة غير صحيحة.',
        ];
    }
}