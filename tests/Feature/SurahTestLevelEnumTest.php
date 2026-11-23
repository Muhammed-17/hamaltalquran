<?php

/**
 * مستويات نتيجة اختبار السورة كـ enum مستقل (ذيله «إعادة» لا «ضعيف»):
 * التحقق يرفض قيم مقياس المتابعة الخاطئة هنا، والقراءة ترجع حالات enum،
 * وألوان العرض تأتي من الـenum (خريطة العرض اليدوية القديمة كانت مفتاحها
 * «جيد جدا» بلا همزة و«ضعيف» بدل «إعادة» فكانت النتيجتان تظهران رماديتين).
 */

use App\Enums\SurahTestLevel;
use App\Http\Requests\SurahTest\StoreSurahTestRequest;
use App\Models\StudentSurahTestResult;
use Illuminate\Support\Facades\Validator;

test('surah test level enum: values, validation and colors', function () {
    expect(SurahTestLevel::values())->toBe(['ممتاز', 'جيد جداً', 'جيد', 'مقبول', 'إعادة'])
        ->and(SurahTestLevel::Repeat->colorClasses())->toContain('red')
        ->and(SurahTestLevel::VeryGood->colorClasses())->toContain('blue');

    $rule = (new StoreSurahTestRequest)->rules()['results.*.level'];
    expect(Validator::make(['results' => [['level' => 'إعادة']]], ['results.*.level' => $rule])->passes())->toBeTrue()
        ->and(Validator::make(['results' => [['level' => 'ضعيف']]], ['results.*.level' => $rule])->fails())->toBeTrue();

    $result = new StudentSurahTestResult;
    $result->setAttribute('level', 'جيد جداً');
    expect($result->level)->toBe(SurahTestLevel::VeryGood);
});
