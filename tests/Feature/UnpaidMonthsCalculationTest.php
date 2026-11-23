<?php

/**
 * وظيفة CalculateUnpaidMonths: شهور الالتحاق تُحسب كمجموعة بلا تكرار —
 * شهر يقع في فترتي إسناد متداخلتين (نقل بين حلقتين في نفس الشهر)
 * كان يُحسب مرتين فيضخّم عدّاد الشهور غير المدفوعة.
 */

use App\Jobs\CalculateUnpaidMonths;
use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\CircleAssignmentHistory;
use App\Models\Student;
use App\Models\StudentUnpaidMonths;
use App\Models\Subscription;

function unpaidFixtureStudent(Center $center): Student
{
    return Student::create(['name' => 'طالب', 'gender' => 'ذكر', 'center_id' => $center->id, 'status' => 'مقيد']);
}

function unpaidCountFor(Student $student): int
{
    (new CalculateUnpaidMonths($student->id))->handle();
    return (int) StudentUnpaidMonths::where('student_id', $student->id)->value('unpaid_months_count');
}

test('overlapping assignments count the shared month once', function () {
    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    $c1 = Circle::create(['name' => 'حلقة 1', 'branch_id' => $branch->id, 'type' => 'group', 'level' => 'build']);
    $c2 = Circle::create(['name' => 'حلقة 2', 'branch_id' => $branch->id, 'type' => 'group', 'level' => 'build']);
    $s = unpaidFixtureStudent($center);
    CircleAssignmentHistory::create(['student_id' => $s->id, 'circle_id' => $c1->id, 'from_date' => '2026-01-01', 'to_date' => '2026-03-31']);
    CircleAssignmentHistory::create(['student_id' => $s->id, 'circle_id' => $c2->id, 'from_date' => '2026-03-01', 'to_date' => null]);
    $this->travelTo(now()->setDate(2026, 3, 15));

    // يناير وفبراير ومارس = 3 (مارس في الفترتين معًا)
    expect(unpaidCountFor($s))->toBe(3);
});

test('paid month inside the overlap is excluded once, other months counted once', function () {
    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    $c1 = Circle::create(['name' => 'حلقة 1', 'branch_id' => $branch->id, 'type' => 'group', 'level' => 'build']);
    $c2 = Circle::create(['name' => 'حلقة 2', 'branch_id' => $branch->id, 'type' => 'group', 'level' => 'build']);
    $s = unpaidFixtureStudent($center);
    CircleAssignmentHistory::create(['student_id' => $s->id, 'circle_id' => $c1->id, 'from_date' => '2026-01-01', 'to_date' => '2026-03-31']);
    CircleAssignmentHistory::create(['student_id' => $s->id, 'circle_id' => $c2->id, 'from_date' => '2026-03-01', 'to_date' => null]);
    Subscription::create(['student_id' => $s->id, 'circle_id' => $c2->id, 'month' => '2026-03-01', 'status' => 'مدفوع', 'amount' => 100]);
    $this->travelTo(now()->setDate(2026, 5, 15));

    // الشهور: يناير..مايو = 5، المدفوع مارس → غير المدفوع 4
    expect(unpaidCountFor($s))->toBe(4);
});

test('student without assignments has zero unpaid months', function () {
    $center = Center::create(['name' => 'مركز']);
    $s = unpaidFixtureStudent($center);

    expect(unpaidCountFor($s))->toBe(0);
});
