<?php

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\StudentConstructionDetail;
use App\Models\SubscriptionPrice;
use App\Models\Surah;
use App\Models\SurahTest;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/*
|--------------------------------------------------------------------------
| اختبارات بوابات الصلاحيات التي كانت وهمية (المشكلة 13)
|--------------------------------------------------------------------------
| كل اختبار يثبت أن بوابة كانت مغلقة على الجميع بسبب اسم صلاحية
| غير معرّف أو مكتوب بصيغة مختلفة أصبحت تعمل لمن يملك الصلاحية.
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');

    $this->center = Center::create(['name' => 'مركز الاختبار']);
    $this->branch = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع الاختبار']);
    $this->circle = Circle::create([
        'branch_id' => $this->branch->id,
        'name'      => 'حلقة الاختبار',
        'type'      => 'بنين',
        'level'     => 'مبتدئ',
    ]);
});

test('registrar filter lists users who recorded attendance', function () {
    $recorder = User::factory()->create(['status' => 'active']);
    $recorder->assignRole('teacher');

    $student = Student::create([
        'name'      => 'طالب حضور',
        'gender'    => 'ذكر',
        'status'    => 'مقيد',
        'circle_id' => $this->circle->id,
    ]);

    Attendance::create([
        'student_id' => $student->id,
        'date'       => now()->format('Y-m-d'),
        'status'     => 'present',
        'user_id'    => $recorder->id,
    ]);

    $response = $this->actingAs($this->admin)->get('/attendance');

    $response->assertOk();
    $response->assertViewHas('registrars', fn ($registrars) => $registrars->contains('id', $recorder->id));
});

test('admin can edit surah test percentage on the create form', function () {
    $response = $this->actingAs($this->admin)->get('/surah-tests/create/individual');

    $response->assertOk();
    $response->assertViewHas('canEditPercentage', true);
});

test('surah test edit button is visible to users with update permission', function () {
    $surah = Surah::create([
        'number'       => 1,
        'name_arabic'  => 'الفاتحة',
        'name_english' => 'Al-Fatiha',
        'type'         => 'meccan',
        'total_ayahs'  => 7,
        'juz_number'   => 1,
        'page_start'   => 1,
        'page_end'     => 1,
    ]);

    $teacherUser = User::factory()->create(['status' => 'active']);
    $teacher = Teacher::create(['user_id' => $teacherUser->id]);

    $surahTest = SurahTest::create([
        'center_id'  => $this->center->id,
        'circle_id'  => $this->circle->id,
        'surah_id'   => $surah->id,
        'teacher_id' => $teacher->id,
        'test_date'  => now()->format('Y-m-d'),
        'test_type'  => 'individual',
    ]);

    $response = $this->actingAs($this->admin)->get(route('surah-tests.show', $surahTest));

    $response->assertOk();
    $response->assertSee(route('surah-tests.edit', $surahTest));
});

test('circle group plan delete button is visible with delete students permission', function () {
    $groupCircle = Circle::create([
        'branch_id' => $this->branch->id,
        'name'      => 'حلقة جماعية',
        'type'      => 'group',
        'level'     => 'مبتدئ',
    ]);

    $plan = StudentConstructionDetail::create([
        'circle_id'    => $groupCircle->id,
        'student_id'   => null,
        'study_system' => 'group',
    ]);

    $response = $this->actingAs($this->admin)->get(route('circles.show', $groupCircle));

    $response->assertOk();
    $response->assertSee(route('student-construction-details.destroy', $plan));
});

test('subscription price policy grants view and update to permitted admin', function () {
    $price = SubscriptionPrice::create([
        'amount'          => 100,
        'circle_level'    => 'مبتدئ',
        'education_stage' => 'ابتدائي',
    ]);

    expect($this->admin->can('viewAny', SubscriptionPrice::class))->toBeTrue()
        ->and($this->admin->can('view', $price))->toBeTrue()
        ->and($this->admin->can('update', $price))->toBeTrue();
});
