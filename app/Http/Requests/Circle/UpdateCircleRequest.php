<?php

namespace App\Http\Requests\Circle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\UserAccessService;
use App\Models\Circle;

class UpdateCircleRequest extends FormRequest
{

    public function authorize(): bool
    {
        $circle = Circle::find($this->route('circle'));
        if (!$circle) return false;
        return $this->user()->can('edit circles') && $this->user()->can('update', $circle);
    }

    public function rules(): array
    {
        $circleId = $this->route('circle');
        $circle   = Circle::find($circleId);
        $access   = app(UserAccessService::class);

        $accessibleCenterIds = $access->accessibleCenters($this->user())->pluck('id');
        $accessibleBranchIds = \App\Models\Branch::whereIn('center_id', $accessibleCenterIds)->pluck('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:circles,name,' . $circleId],
            'url' => ['nullable', 'url', 'max:255'],
            'type' => 'sometimes|required|string',
            'level' => 'sometimes|required|string',

            'branch_id' => $this->user()->hasRole('admin')
                ? ['required', 'exists:branches,id', Rule::in($accessibleBranchIds)]
                : ['sometimes', 'nullable', 'exists:branches,id', Rule::in($accessibleBranchIds)],

            'teacher_id' => [
                'required',
                'exists:teachers,id',
            ],

            'assistant_teacher_id' => [
                'nullable',
                'exists:teachers,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'حقل الاسم مطلوب.',
            'name.string' => 'حقل الاسم يجب أن يكون نصًا.',
            'name.max' => 'حقل الاسم لا يجب أن يتجاوز 255 حرفًا.',
            'name.unique' => 'اسم الحلقة مستخدم بالفعل، يرجى اختيار اسم آخر.',
            'url.url' => 'رابط الحلقة يجب أن يكون رابطاً صحيحاً.',
            'url.max' => 'رابط الحلقة لا يجب أن يتجاوز 255 حرفًا.',
            'type.required' => 'حقل النوع مطلوب.',
            'type.string' => 'حقل النوع يجب أن يكون نصًا.',
            'level.required' => 'حقل المستوى مطلوب.',
            'level.string' => 'حقل المستوى يجب أن يكون نصًا.',
            'branch_id.required' => 'حقل الفرع مطلوب.',
            'branch_id.exists' => 'الفرع المحدد غير موجود.',
            'branch_id.in' => 'الفرع المحدد غير متاح لك.',
            'teacher_id.required' => 'حقل المعلم الرئيسي مطلوب.',
            'teacher_id.exists' => 'المعلم الرئيسي المحدد غير موجود.',
            'assistant_teacher_id.exists' => 'المعلم المساعد المحدد غير موجود.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // فقط branch_id يُقفل لغير admin/general_manager
        if (!$this->user()->hasRole(['admin', 'general_manager'])) {
            $circle = Circle::find($this->route('circle'));
            if ($circle) {
                $this->merge([
                    'branch_id' => $circle->branch_id,
                ]);
            }
        }

        // تنسيق الاسم فقط إذا أُرسل فعلاً
        if ($this->has('name')) {
            $name = trim($this->name);
            $this->merge([
                'name' => str_starts_with($name, 'حلقة') ? $name : 'حلقة ' . $name,
            ]);
        }
    }
}
