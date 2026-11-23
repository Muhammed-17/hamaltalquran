<?php

namespace App\Http\Requests\WeeklyFollowup;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSingleWeeklyFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // التفويض بيتم في الـ Controller عبر $this->authorize()
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string',

            'memorization_from_surah_id' => 'nullable|exists:surahs,id',
            'memorization_from_ayah'     => 'nullable|integer|min:1',
            'memorization_to_surah_id'   => 'nullable|exists:surahs,id',
            'memorization_to_ayah'       => 'nullable|integer|min:1',
            'memorization_plan_comparison' => 'nullable|string',
            'memorization_progress_difference' => 'nullable|string',
            'memorization_notes'         => 'nullable|string',
            'new_memorization_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],

            'revision_from_surah_id' => 'nullable|exists:surahs,id',
            'revision_from_ayah'     => 'nullable|integer|min:1',
            'revision_to_surah_id'   => 'nullable|exists:surahs,id',
            'revision_to_ayah'       => 'nullable|integer|min:1',
            'revision_plan_comparison' => 'nullable|string',
            'revision_progress_difference' => 'nullable|string',
            'revision_notes'         => 'nullable|string',
            'revision_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],

            'old_revision_from_surah_id' => 'nullable|exists:surahs,id',
            'old_revision_from_ayah'     => 'nullable|integer|min:1',
            'old_revision_to_surah_id'   => 'nullable|exists:surahs,id',
            'old_revision_to_ayah'       => 'nullable|integer|min:1',
            'old_revision_plan_comparison' => 'nullable|string',
            'old_revision_progress_difference' => 'nullable|string',
            'old_revision_notes'         => 'nullable|string',
            'old_memorization_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],

            'discipline_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'discipline_achievement' => 'nullable|string',

            'tajweed_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'tajweed_achievement' => 'nullable|string',

            'foundation_level_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'foundation_level_achievement' => 'nullable|string',

            'educational_lesson_level' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AssessmentLevel::class)],
            'educational_lesson_notes' => 'nullable|string',
        ];
    }
}
