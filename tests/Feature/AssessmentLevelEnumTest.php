<?php

/**
 * مقياس تقييم المتابعة الأسبوعية كـ enum: نفس القيم العربية المخزنة،
 * وقواعد التحقق في طلبات المتابعة (فردي وجماعي) ترفض أي قيمة خارج المقياس —
 * الطلبات الجماعية كانت تقبل أي نص قبل التحويل — وقراءة النماذج الفرعية
 * ترجع حالات enum، وعتبات النسبة المئوية مصدرها الـenum نفسه.
 */

use App\Enums\AssessmentLevel;
use App\Http\Requests\WeeklyFollowup\StoreGroupWeeklyFollowupRequest;
use App\Http\Requests\WeeklyFollowup\StoreIndividualWeeklyFollowupRequest;
use App\Models\StudentWeeklyDiscipline;
use App\Models\StudentWeeklyNewMemorization;
use Illuminate\Support\Facades\Validator;

test('assessment level enum: values and percentage thresholds', function () {
    expect(AssessmentLevel::values())->toBe(['ممتاز', 'جيد جداً', 'جيد', 'مقبول', 'ضعيف'])
        ->and(AssessmentLevel::forPercentage(95))->toBe(AssessmentLevel::Excellent)
        ->and(AssessmentLevel::forPercentage(90))->toBe(AssessmentLevel::Excellent)
        ->and(AssessmentLevel::forPercentage(85))->toBe(AssessmentLevel::VeryGood)
        ->and(AssessmentLevel::forPercentage(70))->toBe(AssessmentLevel::Good)
        ->and(AssessmentLevel::forPercentage(60))->toBe(AssessmentLevel::Acceptable)
        ->and(AssessmentLevel::forPercentage(59.5))->toBe(AssessmentLevel::Weak);
});

test('weekly followup requests reject levels outside the scale', function () {
    $individualRules = (new StoreIndividualWeeklyFollowupRequest)->rules();
    $groupRules = (new StoreGroupWeeklyFollowupRequest)->rules();

    foreach (['new_memorization_level', 'revision_level', 'old_memorization_level', 'discipline_level',
              'tajweed_level', 'foundation_level_level', 'educational_lesson_level'] as $field) {
        expect(Validator::make([$field => 'خارق'], [$field => $individualRules[$field]])->fails())->toBeTrue()
            ->and(Validator::make([$field => 'ممتاز'], [$field => $individualRules[$field]])->passes())->toBeTrue()
            ->and(Validator::make(['students' => [[$field => 'خارق']]], ["students.*.{$field}" => $groupRules["students.*.{$field}"]])->fails())->toBeTrue()
            ->and(Validator::make(['students' => [[$field => 'ضعيف']]], ["students.*.{$field}" => $groupRules["students.*.{$field}"]])->passes())->toBeTrue();
    }
});

test('weekly sub-model level attributes read back as enum cases', function () {
    $discipline = new StudentWeeklyDiscipline;
    $discipline->setAttribute('level', 'ضعيف');
    $memo = new StudentWeeklyNewMemorization;
    $memo->setAttribute('average_level', 'ممتاز');
    expect($discipline->level)->toBe(AssessmentLevel::Weak)
        ->and($memo->average_level)->toBe(AssessmentLevel::Excellent);
});
