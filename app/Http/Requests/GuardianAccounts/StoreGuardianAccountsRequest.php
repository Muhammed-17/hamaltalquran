<?php

namespace App\Http\Requests\GuardianAccounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use App\Models\User;

class StoreGuardianAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', 'unique:users,email'],
            'center_id' => ['nullable', 'exists:centers,id'],
            'password'  => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب.',
            'name.string'   => 'الاسم يجب أن يكون نصًا.',
            'name.max'      => 'الاسم يجب ألا يتجاوز :max حرفًا.',

            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email'    => 'البريد الإلكتروني يجب أن يكون عنوانًا صالحًا.',
            'email.max'      => 'البريد الإلكتروني يجب ألا يتجاوز :max حرفًا.',
            'email.unique'   => 'هذا البريد الإلكتروني مستخدم بالفعل.',

            'center_id.exists' => 'الفرع المحدد غير موجود.',

            'password.required'  => 'كلمة المرور مطلوبة.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'password.min'       => 'كلمة المرور يجب أن تكون على الأقل :min أحرف.',
        ];
    }
}
