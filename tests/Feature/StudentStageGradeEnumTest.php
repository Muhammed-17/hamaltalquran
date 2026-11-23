<?php

/**
 * المرحلة الدراسية والصف الدراسي كـ enums: التحقق يرفض القيم الخارجة،
 * والقراءة ترجع حالات، وخريطة ترقية الصف للعام الجديد من الـenum،
 * واستثناء «خريج» في خدمة الاستثناءات يعمل بالحالة لا بالنص.
 */

use App\Enums\EducationalStage;
use App\Enums\SchoolGrade;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Models\Center;
use App\Models\Student;
use App\Services\EducationStageExclusionService;
use Illuminate\Support\Facades\Validator;

test('educational stage and school grade enums: values, promotion and validation', function () {
    expect(EducationalStage::values())->toBe(['تمهيدي', 'حضانة', 'ابتدائي', 'اعدادي', 'ثانوي', 'جامعي', 'خريج'])
        ->and(SchoolGrade::values())->toBe(['لا يوجد', 'الأول', 'الثاني', 'الثالث', 'الرابع', 'الخامس', 'السادس', 'دراسات عليا'])
        ->and(SchoolGrade::First->promoted())->toBe(SchoolGrade::Second)
        ->and(SchoolGrade::Sixth->promoted())->toBe(SchoolGrade::None)
        ->and(SchoolGrade::PostGraduate->promoted())->toBeNull()
        ->and(SchoolGrade::None->promoted())->toBeNull();

    $rules = (new StoreStudentRequest)->rules();
    expect(Validator::make(['educational_stage' => 'ابتدائي'], ['educational_stage' => $rules['educational_stage']])->passes())->toBeTrue()
        ->and(Validator::make(['educational_stage' => 'مرحلة مخترعة'], ['educational_stage' => $rules['educational_stage']])->fails())->toBeTrue()
        ->and(Validator::make(['school_grade' => 'السابع'], ['school_grade' => $rules['school_grade']])->fails())->toBeTrue();
});

test('graduate stage is fully protected in the exclusion service', function () {
    $center = Center::create(['name' => 'مركز']);
    $student = Student::create([
        'name' => 'خريج', 'gender' => 'ذكر', 'center_id' => $center->id,
        'educational_stage' => 'خريج', 'school_grade' => 'لا يوجد', 'status' => 'مقيد',
    ]);
    expect($student->fresh()->educational_stage)->toBe(EducationalStage::Graduate)
        ->and((new EducationStageExclusionService)->evaluate($student))
        ->toMatchArray(['skip' => true, 'reason' => 'مرحلة محمية بالكامل (خريج)']);
});
