<?php

namespace App\Http\Requests\WeeklyFollowup;

use App\Models\Circle;
use App\Services\UserAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreGroupWeeklyFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user   = Auth::user();
        $access = app(UserAccessService::class);

        // بلا مستخدم مصادَق (استدعاء القواعد وحدويًا) نعود لقواعد الوجود فقط
        if (!$user) {
            return [
                'week_start'    => ['required', 'date', 'date_format:Y-m-d'],
                'week_end'      => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:week_start'],
                'circle_id'     => ['required', 'integer', 'exists:circles,id'],
                'teacher_id'    => ['required', 'integer', 'exists:teachers,id'],
            ] + $this->restRules();
        }

        // نفس قيود طلب التعديل حرفيًا: الحلقة من حلقات المستخدم الجماعية فقط،
        // والمعلم من معلميه المتاحين (والمعلم العادي لا يسمّي إلا نفسه) —
        // الوجود وحده (exists) كان يسمح بتسمية معلم مركز آخر وحلقة فردية.
        if ($user->hasRole(['admin', 'general_manager'])) {
            $accessibleCircleIds = Circle::where('type', 'group')->pluck('id')->toArray();
        } else {
            if ($user->hasRole('manager')) {
                $circleIds = $access->managerCircleIds($user);
            } elseif ($user->hasRole('supervisor')) {
                $circleIds = $access->supervisorCircleIds($user);
            } else {
                $circleIds = $access->teacherCircleIdsWithinCenter($user);
            }
            $accessibleCircleIds = Circle::where('type', 'group')
                ->whereIn('id', $circleIds)
                ->pluck('id')
                ->toArray();
        }

        if ($user->hasRole(['admin', 'general_manager', 'manager', 'supervisor'])) {
            $accessibleTeacherIds = $access->accessibleTeachers($user)->pluck('id')->toArray();
        } else {
            $currentTeacher = $access->teacher($user);
            $accessibleTeacherIds = $currentTeacher ? [$currentTeacher->id] : [];
        }

        return array_merge([
            // Week Data
            'week_start'    => ['required', 'date', 'date_format:Y-m-d'],
            'week_end'      => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:week_start'],
            'circle_id'     => ['required', 'integer', Rule::in($accessibleCircleIds)],
            'teacher_id'    => ['required', 'integer', Rule::in($accessibleTeacherIds)],
        ], $this->restRules());
    }

    private function restRules(): array
    {
        return [
            'study_days'    => ['nullable', 'array'],
            'study_days.*'  => ['string', 'in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday'],

            // Plan Data - New Memorization
            'new_memorization.from_surah_id'       => ['nullable', 'integer', 'exists:surahs,id'],
            'new_memorization.from_ayah'           => ['nullable', 'integer', 'min:1'],
            'new_memorization.to_surah_id'         => ['nullable', 'integer', 'exists:surahs,id'],
            'new_memorization.to_ayah'             => ['nullable', 'integer', 'min:1'],
            'new_memorization.plan_comparison'     => ['nullable', 'string', 'max:255'],
            'new_memorization.progress_difference' => ['nullable', 'string', 'max:255'],
            'new_memorization.notes'               => ['nullable', 'string'],

            // Plan Data - Revision
            'revision.from_surah_id'       => ['nullable', 'integer', 'exists:surahs,id'],
            'revision.from_ayah'           => ['nullable', 'integer', 'min:1'],
            'revision.to_surah_id'         => ['nullable', 'integer', 'exists:surahs,id'],
            'revision.to_ayah'             => ['nullable', 'integer', 'min:1'],
            'revision.plan_comparison'     => ['nullable', 'string', 'max:255'],
            'revision.progress_difference' => ['nullable', 'string', 'max:255'],
            'revision.notes'               => ['nullable', 'string'],

            // Plan Data - Old Memorization
            'old_memorization.from_surah_id'       => ['nullable', 'integer', 'exists:surahs,id'],
            'old_memorization.from_ayah'           => ['nullable', 'integer', 'min:1'],
            'old_memorization.to_surah_id'         => ['nullable', 'integer', 'exists:surahs,id'],
            'old_memorization.to_ayah'             => ['nullable', 'integer', 'min:1'],
            'old_memorization.plan_comparison'     => ['nullable', 'string', 'max:255'],
            'old_memorization.progress_difference' => ['nullable', 'string', 'max:255'],
            'old_memorization.notes'               => ['nullable', 'string'],

            // Shared Achievement Notes (batch-level, replicated per student row)
            'discipline_achievement'       => ['nullable', 'string'],
            'tajweed_achievement'          => ['nullable', 'string'],
            'foundation_level_achievement' => ['nullable', 'string'],

            // Educational Lesson
            'educational_lesson_id'          => ['nullable', 'integer', 'exists:educational_lessons,id'],
            'educational_lesson_achievement' => ['nullable', 'string'],

            // Activities
            'activities'               => ['nullable', 'array'],
            'activities.*.id'            => ['nullable', 'integer', 'exists:student_activities,id'],
            'activities.*.activity_type' => ['nullable', 'string', 'max:255'],
            'activities.*.activity_name' => ['nullable', 'string', 'max:255'],
            'activities.*.activity_date' => ['nullable', 'date'],
            'activities.*.notes'         => ['nullable', 'string'],
            'activities.*._deleted'      => ['nullable'],

            // Students Data
            'students'              => ['required', 'array', 'min:1'],
            'students.*.student_id' => ['required', 'integer', 'exists:students,id'],

            // Student assessment levels
            'students.*.discipline_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.tajweed_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.educational_lesson_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.educational_lesson_notes' => ['nullable', 'string'],
            'students.*.foundation_level_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.new_memorization_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.revision_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.old_memorization_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.general_notes'            => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // Week Data
            'week_start.required'    => 'تاريخ بداية الأسبوع مطلوب.',
            'week_start.date'        => 'تاريخ بداية الأسبوع يجب أن يكون تاريخًا صحيحًا.',
            'week_start.date_format' => 'صيغة تاريخ بداية الأسبوع يجب أن تكون Y-m-d.',

            'week_end.required'       => 'تاريخ نهاية الأسبوع مطلوب.',
            'week_end.date'           => 'تاريخ نهاية الأسبوع يجب أن يكون تاريخًا صحيحًا.',
            'week_end.date_format'    => 'صيغة تاريخ نهاية الأسبوع يجب أن تكون Y-m-d.',
            'week_end.after_or_equal' => 'تاريخ نهاية الأسبوع يجب أن يكون مساويًا أو بعد تاريخ بداية الأسبوع.',

            'circle_id.required' => 'الحلقة مطلوبة.',
            'circle_id.integer'  => 'معرف الحلقة يجب أن يكون رقمًا صحيحًا.',
            'circle_id.exists'   => 'الحلقة المختارة غير موجودة.',
            'circle_id.in'       => 'الحلقة المختارة خارج نطاق حلقاتك الجماعية.',

            'teacher_id.required' => 'المعلم مطلوب.',
            'teacher_id.integer'  => 'معرف المعلم يجب أن يكون رقمًا صحيحًا.',
            'teacher_id.exists'   => 'المعلم المختار غير موجود.',
            'teacher_id.in'       => 'المعلم المختار خارج نطاق صلاحياتك.',

            'study_days.array'      => 'أيام الدراسة يجب أن تكون قائمة.',
            'study_days.*.string'   => 'اليوم يجب أن يكون نصًا.',
            'study_days.*.in'       => 'اليوم المحدد غير صحيح.',

            // Plan Data - New Memorization
            'new_memorization.from_surah_id.integer' => 'سورة البداية في الحفظ الجديد يجب أن تكون رقمًا صحيحًا.',
            'new_memorization.from_surah_id.exists'  => 'سورة البداية في الحفظ الجديد غير موجودة.',
            'new_memorization.from_ayah.integer'     => 'رقم آية البداية في الحفظ الجديد يجب أن يكون رقمًا صحيحًا.',
            'new_memorization.from_ayah.min'         => 'رقم آية البداية في الحفظ الجديد يجب ألا يقل عن :min.',

            'new_memorization.to_surah_id.integer' => 'سورة النهاية في الحفظ الجديد يجب أن تكون رقمًا صحيحًا.',
            'new_memorization.to_surah_id.exists'  => 'سورة النهاية في الحفظ الجديد غير موجودة.',
            'new_memorization.to_ayah.integer'     => 'رقم آية النهاية في الحفظ الجديد يجب أن يكون رقمًا صحيحًا.',
            'new_memorization.to_ayah.min'         => 'رقم آية النهاية في الحفظ الجديد يجب ألا يقل عن :min.',

            'new_memorization.plan_comparison.string'     => 'مقارنة الخطة للحفظ الجديد يجب أن تكون نصًا.',
            'new_memorization.plan_comparison.max'        => 'مقارنة الخطة للحفظ الجديد يجب ألا تتجاوز :max حرفًا.',
            'new_memorization.progress_difference.string' => 'فرق الإنجاز للحفظ الجديد يجب أن يكون نصًا.',
            'new_memorization.progress_difference.max'    => 'فرق الإنجاز للحفظ الجديد يجب ألا يتجاوز :max حرفًا.',
            'new_memorization.notes.string'               => 'ملاحظات الحفظ الجديد يجب أن تكون نصًا.',

            // Plan Data - Revision
            'revision.from_surah_id.integer' => 'سورة البداية في المراجعة يجب أن تكون رقمًا صحيحًا.',
            'revision.from_surah_id.exists'  => 'سورة البداية في المراجعة غير موجودة.',
            'revision.from_ayah.integer'     => 'رقم آية البداية في المراجعة يجب أن يكون رقمًا صحيحًا.',
            'revision.from_ayah.min'         => 'رقم آية البداية في المراجعة يجب ألا يقل عن :min.',

            'revision.to_surah_id.integer' => 'سورة النهاية في المراجعة يجب أن تكون رقمًا صحيحًا.',
            'revision.to_surah_id.exists'  => 'سورة النهاية في المراجعة غير موجودة.',
            'revision.to_ayah.integer'     => 'رقم آية النهاية في المراجعة يجب أن يكون رقمًا صحيحًا.',
            'revision.to_ayah.min'         => 'رقم آية النهاية في المراجعة يجب ألا يقل عن :min.',

            'revision.plan_comparison.string'     => 'مقارنة الخطة للمراجعة يجب أن تكون نصًا.',
            'revision.plan_comparison.max'        => 'مقارنة الخطة للمراجعة يجب ألا تتجاوز :max حرفًا.',
            'revision.progress_difference.string' => 'فرق الإنجاز للمراجعة يجب أن يكون نصًا.',
            'revision.progress_difference.max'    => 'فرق الإنجاز للمراجعة يجب ألا يتجاوز :max حرفًا.',
            'revision.notes.string'               => 'ملاحظات المراجعة يجب أن تكون نصًا.',

            // Plan Data - Old Memorization
            'old_memorization.from_surah_id.integer' => 'سورة البداية في الربط يجب أن تكون رقمًا صحيحًا.',
            'old_memorization.from_surah_id.exists'  => 'سورة البداية في الربط غير موجودة.',
            'old_memorization.from_ayah.integer'     => 'رقم آية البداية في الربط يجب أن يكون رقمًا صحيحًا.',
            'old_memorization.from_ayah.min'         => 'رقم آية البداية في الربط يجب ألا يقل عن :min.',

            'old_memorization.to_surah_id.integer' => 'سورة النهاية في الربط يجب أن تكون رقمًا صحيحًا.',
            'old_memorization.to_surah_id.exists'  => 'سورة النهاية في الربط غير موجودة.',
            'old_memorization.to_ayah.integer'     => 'رقم آية النهاية في الربط يجب أن يكون رقمًا صحيحًا.',
            'old_memorization.to_ayah.min'         => 'رقم آية النهاية في الربط يجب ألا يقل عن :min.',

            'old_memorization.plan_comparison.string'     => 'مقارنة الخطة للربط يجب أن تكون نصًا.',
            'old_memorization.plan_comparison.max'        => 'مقارنة الخطة للربط يجب ألا تتجاوز :max حرفًا.',
            'old_memorization.progress_difference.string' => 'فرق الإنجاز للربط يجب أن يكون نصًا.',
            'old_memorization.progress_difference.max'    => 'فرق الإنجاز للربط يجب ألا يتجاوز :max حرفًا.',
            'old_memorization.notes.string'               => 'ملاحظات الربط يجب أن تكون نصًا.',

            // Shared Achievement Notes
            'discipline_achievement.string'       => 'إنجاز السلوك والانضباط يجب أن يكون نصًا.',
            'tajweed_achievement.string'          => 'إنجاز التجويد يجب أن يكون نصًا.',
            'foundation_level_achievement.string' => 'إنجاز القاعدة النورانية يجب أن يكون نصًا.',

            // Educational Lesson
            'educational_lesson_id.integer'          => 'معرف الدرس التربوي يجب أن يكون رقمًا صحيحًا.',
            'educational_lesson_id.exists'           => 'الدرس التربوي المختار غير موجود.',
            'educational_lesson_achievement.string' => 'إنجاز الدرس التربوي يجب أن يكون نصًا.',

            // Activities
            'activities.array'                    => 'الأنشطة يجب أن تكون قائمة.',
            'activities.*.id.integer'             => 'معرف النشاط يجب أن يكون رقمًا صحيحًا.',
            'activities.*.id.exists'              => 'النشاط المحدد غير موجود.',
            'activities.*.activity_type.string'   => 'نوع النشاط يجب أن يكون نصًا.',
            'activities.*.activity_type.max'      => 'نوع النشاط يجب ألا يتجاوز :max حرفًا.',
            'activities.*.activity_name.string'   => 'اسم النشاط يجب أن يكون نصًا.',
            'activities.*.activity_name.max'      => 'اسم النشاط يجب ألا يتجاوز :max حرفًا.',
            'activities.*.activity_date.date'     => 'تاريخ النشاط يجب أن يكون تاريخًا صحيحًا.',
            'activities.*.notes.string'           => 'ملاحظات النشاط يجب أن تكون نصًا.',

            // Students Data
            'students.required' => 'يجب إضافة طالب واحد على الأقل.',
            'students.array'    => 'بيانات الطلاب يجب أن تكون قائمة.',
            'students.min'      => 'يجب إضافة طالب واحد على الأقل.',

            'students.*.student_id.required' => 'الطالب مطلوب.',
            'students.*.student_id.integer'  => 'معرف الطالب يجب أن يكون رقمًا صحيحًا.',
            'students.*.student_id.exists'   => 'الطالب المختار غير موجود.',

            // Student assessment levels
            'students.*.discipline_level.string'         => 'مستوى الانضباط يجب أن يكون نصًا.',
            'students.*.discipline_level.max'            => 'مستوى الانضباط يجب ألا يتجاوز :max حرفًا.',
            'students.*.tajweed_level.string'            => 'مستوى التجويد يجب أن يكون نصًا.',
            'students.*.tajweed_level.max'               => 'مستوى التجويد يجب ألا يتجاوز :max حرفًا.',
            'students.*.educational_lesson_level.string' => 'مستوى الدرس التربوي يجب أن يكون نصًا.',
            'students.*.educational_lesson_level.max'    => 'مستوى الدرس التربوي يجب ألا يتجاوز :max حرفًا.',
            'students.*.educational_lesson_notes.string' => 'ملاحظات الدرس التربوي يجب أن تكون نصًا.',
            'students.*.foundation_level_level.string'   => 'مستوى القاعدة النورانية يجب أن يكون نصًا.',
            'students.*.foundation_level_level.max'      => 'مستوى القاعدة النورانية يجب ألا يتجاوز :max حرفًا.',
            'students.*.new_memorization_level.string'   => 'مستوى الحفظ الجديد يجب أن يكون نصًا.',
            'students.*.new_memorization_level.max'      => 'مستوى الحفظ الجديد يجب ألا يتجاوز :max حرفًا.',
            'students.*.revision_level.string'           => 'مستوى المراجعة يجب أن يكون نصًا.',
            'students.*.revision_level.max'              => 'مستوى المراجعة يجب ألا يتجاوز :max حرفًا.',
            'students.*.old_memorization_level.string'   => 'مستوى الربط يجب أن يكون نصًا.',
            'students.*.old_memorization_level.max'      => 'مستوى الربط يجب ألا يتجاوز :max حرفًا.',
            'students.*.general_notes.string'            => 'الملاحظات العامة للطالب يجب أن تكون نصًا.',
        ];
    }
}
