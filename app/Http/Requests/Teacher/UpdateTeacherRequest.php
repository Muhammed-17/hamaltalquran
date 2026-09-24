<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // ✅ FIX: معرف المعلم قد يصل عبر route param باسم "teacher" أو "id" حسب تعريف الـ route
        $teacherId = $this->route('teacher') ?? $this->route('id');

        return [
            'name'  => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore(
                    $teacherId instanceof \App\Models\Teacher ? $teacherId->user_id : $teacherId,
                    'id'
                ),
            ],
            // ✅ اختيارية في التعديل — تُترك فارغة لعدم تغيير كلمة المرور
            'password'         => 'nullable|string|min:6',
            'current_password' => 'nullable|string',
            'branch_id'        => 'required|integer|exists:branches,id',
            'roles'            => 'required|array|min:1',
            'roles.*'          => 'required|string|exists:roles,name',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'الاسم مطلوب',
            'email.required'     => 'البريد الإلكتروني مطلوب',
            'email.email'        => 'صيغة البريد الإلكتروني غير صحيحة',
            'email.unique'       => 'البريد الإلكتروني مستخدم مسبقاً',
            'password.min'       => 'كلمة المرور يجب ألا تقل عن 6 أحرف',
            'branch_id.required' => 'المقر مطلوب',
            'branch_id.exists'   => 'المقر المختار غير موجود',
            'roles.required'     => 'نوع المستخدم مطلوب',
            'roles.min'          => 'يجب اختيار نوع مستخدم واحد على الأقل',
            'roles.*.exists'     => 'الدور المختار غير موجود',
        ];
    }
}
