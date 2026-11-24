<?php

namespace App\Http\Requests\Circle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\UserAccessService;
use App\Models\Circle;

class StoreCircleRequest extends FormRequest
{


    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        $user   = $this->user();
        $access = app(UserAccessService::class);
        $accessibleCenterIds = $access->accessibleCenters($user)->pluck('id');

        // ✅ الفروع المتاحة = فروع كل المراكز المتاحة للمستخدم
        $accessibleBranchIds = \App\Models\Branch::whereIn('center_id', $accessibleCenterIds)->pluck('id');

        // ✅ FIX: للمشرف — أضف فروع الحلقات اللي بيعمل عليها (main/assistant)
        if ($user->hasRole('supervisor') && !$user->hasRole(['manager', 'admin', 'general_manager'])) {
            $teacher = $access->teacher($user);
            $supervisorBranchIds = \App\Models\Circle::where(function ($q) use ($teacher) {
                $q->whereHas('mainTeachers', fn($qq) => $qq->where('teachers.id', $teacher?->id))
                    ->orWhereHas('assistantTeachers', fn($qq) => $qq->where('teachers.id', $teacher?->id));
            })
                ->pluck('branch_id')
                ->filter()
                ->unique()
                ->values();
            $accessibleBranchIds = $accessibleBranchIds->merge($supervisorBranchIds);
        }

        return [
            'name' => 'required|string|max:255|unique:circles,name',
            'url' => 'nullable|url|max:255',
            'type' => 'required|string',
            'level' => 'required|string',

            'branch_id' => [
                'required',
                'exists:branches,id',
                Rule::in($accessibleBranchIds),
            ],
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
        $user    = $this->user();
        $access  = app(UserAccessService::class);
        $teacher = $access->teacher($user);

        if (!$this->input('branch_id')) {
            $branchId = $teacher?->branch_id;

            if (!$branchId && $user->hasRole('supervisor')) {
                $firstCircle = Circle::where(function ($q) use ($teacher) {
                    $q->whereHas('mainTeachers', fn($qq) => $qq->where('teachers.id', $teacher?->id))
                        ->orWhereHas('assistantTeachers', fn($qq) => $qq->where('teachers.id', $teacher?->id));
                })->first();
                $branchId = $firstCircle?->branch_id;
            }

            if ($branchId) {
                $this->merge(['branch_id' => $branchId]);
            }
        }

        // تنسيق الاسم
        if ($this->has('name')) {
            $name = trim($this->name);
            $this->merge([
                'name' => str_starts_with($name, 'حلقة') ? $name : 'حلقة ' . $name,
            ]);
        }
    }
}
