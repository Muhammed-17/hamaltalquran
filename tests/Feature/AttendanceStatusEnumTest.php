<?php

/**
 * حالة الحضور كـ enum: التحقق يرفض القيم الخارجة، والقراءة ترجع حالات،
 * والتسمية العربية من الـenum، وعدّ الحضور على مجموعة محمّلة يعمل بالحالات
 * (المقارنة النصية على سمة مكاستة كانت ستصفّر العدّ بصمت).
 */

use App\Enums\AttendanceStatus;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Models\Attendance;
use Illuminate\Support\Facades\Validator;

test('attendance status enum: values, labels, validation and collection counts', function () {
    expect(AttendanceStatus::values())->toBe(['present', 'absent', 'late', 'excused'])
        ->and(AttendanceStatus::Absent->label())->toBe('غائب');

    $rule = (new UpdateAttendanceRequest)->rules()['status'];
    expect(Validator::make(['status' => 'present'], ['status' => $rule])->passes())->toBeTrue()
        ->and(Validator::make(['status' => 'حاضر'], ['status' => $rule])->fails())->toBeTrue();

    $records = collect([
        tap(new Attendance, fn ($a) => $a->setAttribute('status', 'present')),
        tap(new Attendance, fn ($a) => $a->setAttribute('status', 'absent')),
        tap(new Attendance, fn ($a) => $a->setAttribute('status', 'present')),
    ]);
    expect($records->where('status', AttendanceStatus::Present)->count())->toBe(2)
        ->and($records->first()->status_label)->toBe('حاضر')
        ->and($records->first()->status)->toBe(AttendanceStatus::Present);
});
