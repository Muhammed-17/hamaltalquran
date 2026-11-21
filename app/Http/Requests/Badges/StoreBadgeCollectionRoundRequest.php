<?php

namespace App\Http\Requests\Badges;

use App\Models\Badges\BadgeCollectionRound;
use App\Services\Badges\BadgeAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBadgeCollectionRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BadgeCollectionRound::class);
    }

    public function rules(): array
    {
        return [
            'circle_id'       => ['required', Rule::exists('circles', 'id')],
            'teacher_id'      => ['required', Rule::exists('teachers', 'user_id')],
            'collection_date' => ['required', 'date'],
            'notes'           => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'circle_id.required'  => 'يجب اختيار الحلقة.',
            'teacher_id.required' => 'يجب اختيار المعلم.',
            'collection_date.required' => 'تاريخ التحصيل مطلوب.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $access = app(BadgeAccessService::class);
            $circleId = (int) $this->input('circle_id');

            if (!$access->canAccessCircle($this->user(), $circleId)) {
                $v->errors()->add('circle_id', 'الحلقة المحددة خارج نطاق وصولك.');
            }
            if (!$access->teacherResponsibleForCircle((int) $this->input('teacher_id'), $circleId)) {
                $v->errors()->add('teacher_id', 'المعلم المحدد ليس مسؤولًا عن هذه الحلقة.');
            }
        });
    }
}
