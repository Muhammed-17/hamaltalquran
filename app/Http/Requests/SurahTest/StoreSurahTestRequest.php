<?php

namespace App\Http\Requests\SurahTest;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\Student;
use App\Models\StudentSurahTestResult;
use App\Models\SurahTest;

class StoreSurahTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SurahTest::class);
    }

    protected function prepareForValidation(): void
    {
        $results = $this->input('results', []);

        if (!is_array($results)) {
            return;
        }

        foreach ($results as $index => $result) {
            foreach (['prompt_errors', 'tashkeel_errors', 'percentage'] as $field) {
                if (!isset($result[$field])) {
                    continue;
                }

                $value = $this->normalizeDigits($result[$field]);

                $results[$index][$field] = ($value === '' || !is_numeric($value))
                    ? 0
                    : (int) $value;
            }
        }

        $this->merge(['results' => $results]);
    }

    protected function normalizeDigits(mixed $value): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return (string) $value;
        }

        $arabicIndic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $persian     = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $latin       = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $value = str_replace($arabicIndic, $latin, (string) $value);
        $value = str_replace($persian, $latin, $value);

        return trim($value);
    }

    public function rules(): array
    {
        return [
            'test_type'   => ['required', 'in:individual,group'],
            'surah_id'    => ['required', 'exists:surahs,id'],
            'teacher_id'  => ['required', 'exists:teachers,id'],
            'test_date'   => ['required', 'date'],
            'notes'       => ['nullable', 'string'],
            'student_id'  => ['required_if:test_type,individual', 'nullable', 'exists:students,id'],
            'circle_id'   => ['required_if:test_type,group', 'nullable', 'exists:circles,id'],
            'results'                   => ['required', 'array', 'min:1'],
            'results.*.student_id'      => ['required', 'integer'],
            'results.*.prompt_errors'   => ['required', 'integer', 'min:0'],
            'results.*.tashkeel_errors' => ['required', 'integer', 'min:0'],
            'results.*.percentage'      => ['required', 'integer', 'min:0', 'max:100'],
            'results.*.level'           => ['nullable', 'string', 'in:' . implode(',', StudentSurahTestResult::LEVELS)],
            'results.*.notes'           => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'test_type.required'   => 'يرجى اختيار نوع الاختبار.',
            'test_type.in'         => 'نوع الاختبار غير صالح.',

            'surah_id.required'    => 'يرجى اختيار السورة.',
            'surah_id.exists'      => 'السورة المختارة غير موجودة.',

            'teacher_id.required'  => 'يرجى اختيار المعلم.',
            'teacher_id.exists'    => 'المعلم المختار غير موجود.',

            'test_date.required'   => 'يرجى تحديد تاريخ الاختبار.',
            'test_date.date'       => 'تاريخ الاختبار غير صالح.',

            'notes.string'         => 'صيغة الملاحظات غير صحيحة.',

            'student_id.required_if' => 'يرجى اختيار الطالب.',
            'student_id.exists'      => 'الطالب المختار غير موجود.',

            'circle_id.required_if'  => 'يرجى اختيار الحلقة.',
            'circle_id.exists'       => 'الحلقة المختارة غير موجودة.',

            'results.required'       => 'يجب إدخال نتيجة طالب واحد على الأقل.',
            'results.array'          => 'صيغة النتائج غير صحيحة.',
            'results.min'            => 'يجب إدخال نتيجة طالب واحد على الأقل.',

            'results.*.student_id.required' => 'يجب تحديد الطالب لكل نتيجة.',
            'results.*.student_id.integer'  => 'رقم الطالب غير صالح.',

            'results.*.prompt_errors.required' => 'يرجى إدخال عدد أخطاء الفتح.',
            'results.*.prompt_errors.integer'  => 'عدد أخطاء الفتح يجب أن يكون رقمًا صحيحًا.',
            'results.*.prompt_errors.min'      => 'عدد أخطاء الفتح لا يمكن أن يكون أقل من صفر.',

            'results.*.tashkeel_errors.required' => 'يرجى إدخال عدد الأخطاء التشكيلية.',
            'results.*.tashkeel_errors.integer'  => 'عدد الأخطاء التشكيلية يجب أن يكون رقمًا صحيحًا.',
            'results.*.tashkeel_errors.min'      => 'عدد الأخطاء التشكيلية لا يمكن أن يكون أقل من صفر.',

            'results.*.percentage.required' => 'يرجى إدخال النسبة.',
            'results.*.percentage.integer'  => 'النسبة يجب أن تكون رقمًا صحيحًا.',
            'results.*.percentage.min'      => 'النسبة لا يمكن أن تكون أقل من صفر.',
            'results.*.percentage.max'      => 'النسبة لا يمكن أن تتجاوز 100.',

            'results.*.level.string' => 'صيغة التقدير غير صحيحة.',
            'results.*.level.in'     => 'التقدير المختار غير صالح.',

            'results.*.notes.string' => 'صيغة ملاحظات النتيجة غير صحيحة.',
        ];
    }
    /**
     * تحقق إضافي بعد الفاليديشن الأساسية.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $results = $this->input('results', []);
            $studentIds = collect($results)->pluck('student_id')->filter()->unique()->values()->all();

            if (empty($studentIds)) {
                return;
            }

            $existingIds = Student::whereIn('id', $studentIds)
                ->pluck('id')
                ->all();

            $missing = array_diff($studentIds, $existingIds);

            if (!empty($missing)) {
                $validator->errors()->add('results', 'بعض الطلاب المرسلين غير موجودين.');
            }
        });
    }
}
