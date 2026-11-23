<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'date' => 'required|date|before_or_equal:today',
            'circle_id' => 'required|exists:circles,id',
            'attendance' => 'required|array|min:1',
            'attendance.*.student_id' => 'required|exists:students,id',
            'attendance.*.status' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\AttendanceStatus::class)],
            'attendance.*.notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'التاريخ مطلوب',
            'circle_id.required' => 'الحلقة مطلوبة',
            'attendance.required' => 'يجب تسجيل حضور طالب واحد على الأقل',
            'attendance.*.student_id.required' => 'معرف الطالب مطلوب',
            'attendance.*.status.required' => 'حالة الحضور مطلوبة',
            'attendance.*.status.in' => 'حالة الحضور غير صالحة',
            'attendance.*.notes.max' => 'الملاحظات يجب ألا تتجاوز 500 حرف',
        ];
    }
}
