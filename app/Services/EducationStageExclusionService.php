<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Collection;

class EducationStageExclusionService
{

    // ✅ أقصى فرق سنين يُعتبر طبيعي (رسوب/تقديم سنة واحدة) ولا يحتاج مراجعة
    private const NORMAL_AGE_DIFF_TOLERANCE = 1;

    /**
     * يُرجع تقييم شامل لكل طالب: هل يُستثنى من التحديث التلقائي، وهل يحتاج ظهور في صفحة المراجعة.
     *
     * @return array{skip: bool, reason: ?string}
     */
    public function evaluate(Student $student): array
    {
        $thresholdsRaw = config('education_stages.thresholds');
        $excludedGrades = config('education_stages.excluded_grades', []);

        // ✅ خريطة: "المرحلة|الصف" => السن المتوقع لها (من نفس جدول الـ config)
        $ageByStageGrade = collect($thresholdsRaw)->mapWithKeys(
            fn($row, $age) => ["{$row['educational_stage']}|{$row['school_grade']}" => $age]
        );

        if ($student->educational_stage === \App\Enums\EducationalStage::Graduate) {
            return ['skip' => true, 'reason' => 'مرحلة محمية بالكامل (خريج)'];
        }

        if (in_array($student->school_grade?->value, $excludedGrades, true)) {
            return ['skip' => true, 'reason' => 'صف مستثنى (دراسات عليا)'];
        }

        if (!$student->date_of_birth) {
            return ['skip' => true, 'reason' => null];
        }

        $age = $student->date_of_birth->age;

        if ($age < 4) {
            return ['skip' => true, 'reason' => null];
        }

        $key = "{$student->educational_stage?->value}|{$student->school_grade?->value}";

        if (!$ageByStageGrade->has($key)) {
            return ['skip' => true, 'reason' => "تركيبة \"{$student->educational_stage?->value} - {$student->school_grade?->value}\" غير معروفة في جدول المراحل"];
        }

        $expectedAge = $ageByStageGrade->get($key);
        $diff        = $age - $expectedAge;

        if (abs($diff) <= self::NORMAL_AGE_DIFF_TOLERANCE) {
            // فرق سنة واحدة أو مطابق تمامًا — حالة طبيعية (رسوب/تقديم سنة، أو مطابق)
            return ['skip' => true, 'reason' => null];
        }

        $direction = $diff > 0 ? 'متأخر' : 'متقدم';
        return [
            // الحالة الوحيدة القابلة للمزامنة: تركيبة معروفة خارج السماحية —
            // الأمر يحدّث صاحبها لتركيبة سنه، وتبقى ظاهرة في صفحة المراجعة بسببها
            'skip'   => false,
            'reason' => "{$direction} عن صفه الطبيعي بفارق " . abs($diff) . ' سنوات — يحتاج مراجعة',
        ];
    }

    /**
     * يُرجع فقط الطلاب اللي محتاجين ظهور فعلي في صفحة المراجعة (استبعاد الحالات الصامتة).
     */
    public function getExcludedStudents(): Collection
    {
        return Student::query()
            ->select(['id', 'name', 'student_code', 'date_of_birth', 'educational_stage', 'school_grade', 'education_type', 'status'])
            ->whereNotNull('date_of_birth')
            ->get()
            ->map(function (Student $student) {
                $eval = $this->evaluate($student);
                return $eval['reason'] ? (object) ['student' => $student, 'reason' => $eval['reason']] : null;
            })
            ->filter()
            ->values();
    }
}
