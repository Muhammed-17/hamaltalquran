<?php

/**
 * أمر students:sync-education-stage بعد تفعيله: دالة التقييم لا تُخطّي
 * إلا المحمي والمستثنى والمجهول وداخل السماحية، والتركيبة المعروفة
 * الخارجة عن السماحية (فرق سنتين فأكثر) تُحدَّث فعليًا لتركيبة السن.
 */

use App\Models\Center;
use App\Models\Student;
use App\Services\EducationStageExclusionService;
use Illuminate\Support\Facades\Artisan;

function syncStudent(Center $center, string $name, int $ageYears, string $stage, string $grade): Student
{
    return Student::create([
        'name' => $name, 'gender' => 'ذكر', 'center_id' => $center->id, 'status' => 'مقيد',
        'date_of_birth' => now()->subYears($ageYears)->format('Y-m-d'),
        'educational_stage' => $stage, 'school_grade' => $grade,
    ]);
}

test('evaluation skip semantics: only out-of-tolerance known combos are syncable', function () {
    $center = Center::create(['name' => 'مركز']);
    $svc = new EducationStageExclusionService;

    expect($svc->evaluate(syncStudent($center, 'متأخر', 13, 'ابتدائي', 'الخامس'))['skip'])->toBeFalse()
        ->and($svc->evaluate(syncStudent($center, 'راسب', 11, 'ابتدائي', 'الخامس'))['skip'])->toBeTrue()
        ->and($svc->evaluate(syncStudent($center, 'طبيعي', 10, 'ابتدائي', 'الخامس'))['skip'])->toBeTrue()
        ->and($svc->evaluate(syncStudent($center, 'خريج', 22, 'خريج', 'لا يوجد'))['skip'])->toBeTrue()
        ->and($svc->evaluate(syncStudent($center, 'دراسات', 24, 'جامعي', 'دراسات عليا'))['skip'])->toBeTrue()
        ->and($svc->evaluate(syncStudent($center, 'غريب', 6, 'تمهيدي', 'الأول'))['skip'])->toBeTrue();
});

test('sync command updates only the out-of-tolerance students', function () {
    $center = Center::create(['name' => 'مركز']);
    $late = syncStudent($center, 'متأخر', 13, 'ابتدائي', 'الخامس');
    $repeater = syncStudent($center, 'راسب سنة', 11, 'ابتدائي', 'الخامس');
    $graduate = syncStudent($center, 'خريج', 22, 'خريج', 'لا يوجد');
    $postgrad = syncStudent($center, 'دراسات عليا', 24, 'جامعي', 'دراسات عليا');
    $unknown = syncStudent($center, 'تركيبة غريبة', 6, 'تمهيدي', 'الأول');

    Artisan::call('students:sync-education-stage');

    expect($late->fresh()->educational_stage->value)->toBe('اعدادي')
        ->and($late->fresh()->school_grade->value)->toBe('الثاني')
        ->and($repeater->fresh()->school_grade->value)->toBe('الخامس')
        ->and($graduate->fresh()->educational_stage->value)->toBe('خريج')
        ->and($postgrad->fresh()->school_grade->value)->toBe('دراسات عليا')
        ->and($unknown->fresh()->educational_stage->value)->toBe('تمهيدي');
});

test('sync command dry-run changes nothing', function () {
    $center = Center::create(['name' => 'مركز']);
    $late = syncStudent($center, 'متأخر', 13, 'ابتدائي', 'الخامس');

    Artisan::call('students:sync-education-stage', ['--dry-run' => true]);

    expect($late->fresh()->educational_stage->value)->toBe('ابتدائي');
});
