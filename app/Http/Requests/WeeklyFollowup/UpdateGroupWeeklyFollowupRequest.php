<?php

namespace App\Http\Requests\WeeklyFollowup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Models\Circle;
use App\Models\Teacher;
use App\Models\StudentWeeklyFollowup;
use App\Services\UserAccessService;

class UpdateGroupWeeklyFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batchId = $this->route('batchId');
        $sample = StudentWeeklyFollowup::where('batch_id', $batchId)->firstOrFail();
        return Auth::user()->can('update', $sample);
    }

    public function rules(): array
    {
        $user   = Auth::user();
        $access = app(UserAccessService::class);
        $batchId = $this->route('batchId');

        // Get accessible circle IDs
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

        // Get accessible teacher IDs
        if ($user->hasRole(['admin', 'general_manager', 'manager', 'supervisor'])) {
            $accessibleTeacherIds = $access->accessibleTeachers($user)->pluck('id')->toArray();
        } else {
            $currentTeacher = $access->teacher($user);
            $accessibleTeacherIds = $currentTeacher ? [$currentTeacher->id] : [];
        }

        return [
            'circle_id'   => ['required', 'integer', Rule::in($accessibleCircleIds)],
            'teacher_id'  => ['required', 'integer', Rule::in($accessibleTeacherIds)],
            'week_start'  => ['required', 'date', 'date_format:Y-m-d'],
            'week_end'    => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:week_start'],
            'study_days'  => ['nullable', 'array'],
            'study_days.*' => ['string', 'in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday'],

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

            // Shared Achievement Notes (batch-level, replicated per student row)
            'discipline_achievement'       => ['nullable', 'string'],
            'tajweed_achievement'          => ['nullable', 'string'],
            'foundation_level_achievement' => ['nullable', 'string'],

            // Educational Lesson
            'educational_lesson_id' => ['nullable', 'integer', 'exists:educational_lessons,id'],
            'educational_lesson_achievement' => 'nullable|string',

            // Activities
            'activities'              => ['nullable', 'array'],
            'activities.*.id'         => ['nullable', 'integer', 'exists:student_activities,id'],
            'activities.*.activity_type'  => ['nullable', 'string', 'max:255'],
            'activities.*.activity_name'  => ['nullable', 'string', 'max:255'],
            'activities.*.activity_date'  => ['nullable', 'date'],
            'activities.*.notes'          => ['nullable', 'string'],
            'activities.*._deleted'     => ['nullable'],

            // Students Data
            'students'              => ['required', 'array', 'min:1'],
            'students.*.student_id' => ['required', 'integer', 'exists:students,id'],

            // Student assessment levels
            'students.*.discipline_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.tajweed_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.educational_lesson_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.educational_lesson_notes'   => ['nullable', 'string'],
            'students.*.foundation_level_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.new_memorization_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.revision_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.old_memorization_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'students.*.general_notes'              => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // Field-specific custom messages (replacing attributes)
            'circle_id.required'         => 'حقل الحلقة مطلوب.',
            'circle_id.integer'          => 'حقل الحلقة يجب أن يكون رقمًا صحيحًا.',
            'circle_id.in'               => 'القيمة المختارة في حقل الحلقة غير صحيحة.',

            'teacher_id.required'        => 'حقل المعلم مطلوب.',
            'teacher_id.integer'         => 'حقل المعلم يجب أن يكون رقمًا صحيحًا.',
            'teacher_id.in'              => 'القيمة المختارة في حقل المعلم غير صحيحة.',

            'week_start.required'        => 'حقل بداية الأسبوع مطلوب.',
            'week_start.date'            => 'حقل بداية الأسبوع يجب أن يكون تاريخًا صحيحًا.',
            'week_start.date_format'     => 'صيغة حقل بداية الأسبوع غير صحيحة.',

            'week_end.required'          => 'حقل نهاية الأسبوع مطلوب.',
            'week_end.date'              => 'حقل نهاية الأسبوع يجب أن يكون تاريخًا صحيحًا.',
            'week_end.date_format'       => 'صيغة حقل نهاية الأسبوع غير صحيحة.',
            'week_end.after_or_equal'    => 'حقل نهاية الأسبوع يجب أن يكون بعد أو مساويًا لبداية الأسبوع.',

            'study_days.array'           => 'حقل أيام الدراسة يجب أن يكون قائمة.',
            'study_days.*.in'            => 'القيمة المختارة في أيام الدراسة غير صحيحة.',

            'students.required'          => 'يجب إضافة طالب واحد على الأقل.',
            'students.array'             => 'حقل الطلاب يجب أن يكون قائمة.',
            'students.min'               => 'يجب إضافة طالب واحد على الأقل.',

            'students.*.student_id.required' => 'حقل الطالب مطلوب.',
            'students.*.student_id.integer'  => 'حقل الطالب يجب أن يكون رقمًا صحيحًا.',
            'students.*.student_id.exists'   => 'القيمة المختارة في حقل الطالب غير موجودة.',

            'activities.*.activity_type.string' => 'حقل نوع النشاط يجب أن يكون نصًا.',
            'activities.*.activity_type.max'    => 'حقل نوع النشاط يجب ألا يزيد عن 255 حرفًا.',

            'activities.*.activity_name.string' => 'حقل اسم النشاط يجب أن يكون نصًا.',
            'activities.*.activity_name.max'    => 'حقل اسم النشاط يجب ألا يزيد عن 255 حرفًا.',

            'activities.*.activity_date.date'   => 'حقل تاريخ النشاط يجب أن يكون تاريخًا صحيحًا.',

            // General fallback messages for remaining fields
            'required'                   => 'هذا الحقل مطلوب.',
            'integer'                    => 'هذا الحقل يجب أن يكون رقمًا صحيحًا.',
            'string'                     => 'هذا الحقل يجب أن يكون نصًا.',
            'array'                      => 'هذا الحقل يجب أن يكون قائمة.',
            'min'                        => 'هذا الحقل يجب ألا يقل عن :min.',
            'max'                        => 'هذا الحقل يجب ألا يزيد عن :max حرفًا.',
            'date'                       => 'هذا الحقل يجب أن يكون تاريخًا صحيحًا.',
            'date_format'                => 'صيغة هذا الحقل غير صحيحة.',
            'after_or_equal'             => 'هذا الحقل يجب أن يكون بعد أو مساويًا للقيمة المحددة.',
            'exists'                     => 'القيمة المختارة غير موجودة.',
            'in'                         => 'القيمة المختارة غير صحيحة.',
        ];
    }
}
