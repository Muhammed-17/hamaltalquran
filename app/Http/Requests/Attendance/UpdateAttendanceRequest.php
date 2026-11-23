<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('update', $this->route('attendance'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\AttendanceStatus::class)],
            'notes' => 'nullable|string|max:500',
            'date' => 'required|date|before_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'حالة الحضور مطلوبة',
            'status.in' => 'حالة الحضور يجب أن تكون: حاضر، غائب، متأخر، أو بعذر',
            'notes.max' => 'الملاحظات يجب ألا تتجاوز 500 حرف',
            'date.required' => 'التاريخ مطلوب',
            'date.date' => 'التاريخ يجب أن يكون تاريخاً صالحاً',
        ];
    }
}
