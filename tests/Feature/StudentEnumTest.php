<?php

/**
 * تحويل حالة الطالب وقراره وجنسه إلى enums: القيم المخزنة عربية كما هي،
 * والتحقق يرفض أي قيمة خارج المجموعات، والقراءة ترجع حالات enum.
 */

use App\Enums\Gender;
use App\Enums\StudentDecision;
use App\Enums\StudentStatus;
use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    $this->circle = Circle::create(['name' => 'حلقة', 'type' => 'group', 'level' => 'build', 'branch_id' => $branch->id]);
    $this->admin = User::create(['name' => 'admin', 'email' => 'admin@enum.local', 'password' => Hash::make('Test123!'), 'status' => 'active']);
    $this->admin->assignRole('admin');
    $this->center = $center;
});

function enumStudentPayload($test, array $overrides = []): array
{
    return array_merge([
        'name' => 'طالب تجريبي', 'gender' => 'ذكر', 'address' => 'عنوان',
        'center_id' => $test->center->id, 'circle_id' => $test->circle->id,
        'applicant' => 'الأب', 'status' => 'مقيد', 'decision' => 'مقبول',
        'guardian_id' => 'none',
    ], $overrides);
}

test('student store rejects values outside the enum sets', function () {
    $this->actingAs($this->admin)->post(route('students.store'), enumStudentPayload($this, ['status' => 'حالة مخترعة']))
        ->assertSessionHasErrors('status');
    $this->actingAs($this->admin)->post(route('students.store'), enumStudentPayload($this, ['decision' => 'قبول']))
        ->assertSessionHasErrors('decision');
    $this->actingAs($this->admin)->post(route('students.store'), enumStudentPayload($this, ['gender' => 'غير ذلك']))
        ->assertSessionHasErrors('gender');
    expect(Student::count())->toBe(0);
});

test('student attributes read back as enum cases with the same stored Arabic values', function () {
    $student = Student::create([
        'name' => 'طالب', 'center_id' => $this->center->id, 'circle_id' => $this->circle->id,
        'status' => 'متوقف', 'decision' => 'تحت الاختبار', 'gender' => 'أنثى',
    ]);
    $fresh = $student->fresh();
    expect($fresh->status)->toBe(StudentStatus::Stopped)
        ->and($fresh->decision)->toBe(StudentDecision::UnderReview)
        ->and($fresh->gender)->toBe(Gender::Female)
        ->and($fresh->status->value)->toBe('متوقف')
        ->and(StudentStatus::values())->toBe(['مقيد', 'متوقف', 'مسافر']);
});
