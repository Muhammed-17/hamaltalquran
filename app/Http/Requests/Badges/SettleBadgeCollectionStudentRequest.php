<?php

namespace App\Http\Requests\Badges;

use App\Models\Badges\BadgeCollectionRound;
use App\Services\Badges\BadgeAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** تسوية طالب واحد داخل جلسة تحصيل: سليم + تالف/مفقود + خصم من المستحق لكل نوع */
class SettleBadgeCollectionStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BadgeCollectionRound::class);
    }

    public function rules(): array
    {
        return [
            'circle_id'       => ['required', Rule::exists('circles', 'id')],
            'teacher_id'      => ['required', Rule::exists('users', 'id')],
            'collection_date' => ['required', 'date'],
            'student_id'      => ['required', Rule::exists('students', 'id')],
            'amounts'               => ['nullable', 'array'],
            'amounts.*.good'        => ['nullable', 'integer', 'min:0'],
            'amounts.*.damaged'     => ['nullable', 'integer', 'min:0'],
            'amounts.*.owed'        => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'circle_id.required'  => 'يجب اختيار الحلقة.',
            'teacher_id.required' => 'يجب اختيار المسؤول عن التحصيل.',
            'student_id.required' => 'يجب اختيار الطالب.',
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

            // من يدير التحصيل يصل إلى كل الحلقات في كل الفروع والمراكز
            if (!$access->canRunCollections($this->user())) {
                $v->errors()->add('circle_id', 'إدارة جولات التحصيل للمشرفين والمديرين فقط.');
            }
            // مسؤول التحصيل: مشرف أو مدير أو مدير عام نشط — عدا مدير النظام والمعلم
            $collector = \App\Models\User::find((int) $this->input('teacher_id'));
            if (!$collector || $collector->status !== 'active' || $collector->hasRole('admin')
                || !$collector->hasRole(['supervisor', 'manager', 'general_manager'])) {
                $v->errors()->add('teacher_id', 'مسؤول التحصيل يجب أن يكون مشرفًا أو مديرًا أو مديرًا عامًا.');
            }
            // الطالب من طلاب الحلقة (استعلام مباشر بلا نطاق مركز — التحصيل عابر للمراكز)
            $inCircle = DB::table('students')
                ->where('id', (int) $this->input('student_id'))
                ->where('circle_id', (int) $this->input('circle_id'))
                ->exists();
            if (!$inCircle) {
                $v->errors()->add('student_id', 'الطالب المحدد ليس من طلاب هذه الحلقة.');
            }
        });
    }
}
