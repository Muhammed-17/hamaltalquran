<?php
use App\Models\{Branch, Center, Circle, Student, Surah, SurahTest, Teacher, User};
use Illuminate\Support\Facades\DB;
use Database\Seeders\{PermissionSeeder, SurahSeeder};

/**
 * فحص إنشاء اختبار السورة (تقرير 220): الممتحِن كان بلا فحص نطاق، والنسبة
 * والتقدير لا يُعاد حسابهما خادميًا رغم قفلهما على غير الأدمن، وطالب بلا
 * حلقة يتسلل من فحص الغرباء، والفردي غير مرتبط بطالبه، وتاريخ مستقبلي
 * ونوع حلقة غير مطابق يمران، ورسالة SQL خام عند فشل الحفظ.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SurahSeeder::class);
    $this->surah1 = Surah::where('number', 1)->first();  // الفاتحة: أوزان 1 / 0.5
    $this->surah2 = Surah::where('number', 2)->first();  // البقرة: أوزان 2 / 1
    $mk = function (string $role, string $tag) {
        $u = User::create(['name' => $tag, 'email' => $tag . '@t.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->assignRole($role);
        return $u;
    };
    $this->admin = $mk('admin', 'الأدمن');
    $centerA = Center::create(['name' => 'مركز أ']);
    $centerB = Center::create(['name' => 'مركز ب']);
    $branchA = Branch::create(['name' => 'فرع أ', 'center_id' => $centerA->id]);
    $branchB = Branch::create(['name' => 'فرع ب', 'center_id' => $centerB->id]);
    $this->circle = Circle::create(['name' => 'حلقة جماعية', 'branch_id' => $branchA->id, 'type' => 'group', 'level' => 'build', 'status' => 'active']);
    $this->indivCircle = Circle::create(['name' => 'حلقة فردية', 'branch_id' => $branchA->id, 'type' => 'individual', 'level' => 'build', 'status' => 'active']);
    $this->supervisor = $mk('supervisor', 'المشرف');
    $supT = Teacher::create(['user_id' => $this->supervisor->id, 'branch_id' => $branchA->id, 'name' => 'المشرف']);
    DB::table('branch_teacher')->insert(['branch_id' => $branchA->id, 'teacher_id' => $supT->id]);
    $examinerUser = $mk('teacher', 'الممتحن');
    $this->examiner = Teacher::create(['user_id' => $examinerUser->id, 'branch_id' => $branchA->id, 'name' => 'الممتحن']);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circle->id, 'teacher_id' => $this->examiner->id, 'role' => 'main']);
    DB::table('circle_teacher')->insert(['circle_id' => $this->indivCircle->id, 'teacher_id' => $this->examiner->id, 'role' => 'main']);
    $foreignUser = $mk('teacher', 'ممتحن غريب');
    $this->foreignExaminer = Teacher::create(['user_id' => $foreignUser->id, 'branch_id' => $branchB->id, 'name' => 'ممتحن غريب']);
    $s = fn (string $name, ?int $circleId, int $centerId) => Student::create(['name' => $name, 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول', 'center_id' => $centerId, 'circle_id' => $circleId]);
    $this->student = $s('طالب', $this->circle->id, $centerA->id);
    $this->noCircle = $s('طالب بلا حلقة', null, $centerA->id);
    $this->indivA = $s('فردي أ', $this->indivCircle->id, $centerA->id);
    $this->indivB = $s('فردي ب', $this->indivCircle->id, $centerA->id);
    $this->payload = [
        'test_type' => 'group', 'circle_id' => $this->circle->id,
        'teacher_id' => $this->examiner->id, 'surah_id' => $this->surah1->id,
        'test_date' => '2026-11-20',
        'results' => [['student_id' => $this->student->id, 'prompt_errors' => 0, 'tashkeel_errors' => 0, 'percentage' => 100, 'level' => 'ممتاز']],
    ];
});

test('examiner must be inside the actor scope', function () {
    $this->actingAs($this->supervisor)->post(route('surah-tests.store'), array_merge($this->payload, ['teacher_id' => $this->foreignExaminer->id]))
        ->assertSessionHas('error');
    expect(SurahTest::count())->toBe(0);
    $this->actingAs($this->supervisor)->post(route('surah-tests.store'), $this->payload)->assertSessionHas('success');
    expect(SurahTest::count())->toBe(1);
});

test('percentage and level are recomputed server side for non holders', function () {
    // الفاتحة (1/0.5): 10 فتح + 10 تشكيل = 100 − 10 − 5 = 85 «جيد» — لا 100 «ممتاز»
    $this->actingAs($this->supervisor)->post(route('surah-tests.store'), array_merge($this->payload, [
        'results' => [['student_id' => $this->student->id, 'prompt_errors' => 10, 'tashkeel_errors' => 10, 'percentage' => 100, 'level' => 'ممتاز']],
    ]))->assertSessionHas('success');
    $result = SurahTest::first()->results->first();
    expect((int) $result->percentage)->toBe(85)->and($result->level->value)->toBe('جيد');

    // الأدمن حامل صلاحية النسب: قيمه اليدوية تُحفظ كما هي
    $this->actingAs($this->admin)->post(route('surah-tests.store'), array_merge($this->payload, [
        'test_date' => '2026-11-21',
        'results' => [['student_id' => $this->student->id, 'prompt_errors' => 10, 'tashkeel_errors' => 10, 'percentage' => 100, 'level' => 'ممتاز']],
    ]))->assertSessionHas('success');
    $manual = SurahTest::latest('id')->first()->results->first();
    expect((int) $manual->percentage)->toBe(100)->and($manual->level->value)->toBe('ممتاز');
});

test('circle less students and circle type mismatch are rejected', function () {
    $this->actingAs($this->admin)->post(route('surah-tests.store'), array_merge($this->payload, [
        'results' => [['student_id' => $this->noCircle->id, 'prompt_errors' => 0, 'tashkeel_errors' => 0, 'percentage' => 100, 'level' => 'ممتاز']],
    ]))->assertSessionHas('error');
    $this->actingAs($this->supervisor)->post(route('surah-tests.store'), array_merge($this->payload, ['circle_id' => $this->indivCircle->id, 'results' => [['student_id' => $this->indivA->id, 'prompt_errors' => 0, 'tashkeel_errors' => 0, 'percentage' => 100, 'level' => 'ممتاز']]]))
        ->assertSessionHas('error');
    expect(SurahTest::count())->toBe(0);
});

test('individual tests are tied to their single student', function () {
    $base = ['test_type' => 'individual', 'student_id' => $this->indivA->id, 'teacher_id' => $this->examiner->id, 'surah_id' => $this->surah1->id, 'test_date' => '2026-11-20'];
    $row = fn ($id) => ['student_id' => $id, 'prompt_errors' => 0, 'tashkeel_errors' => 0, 'percentage' => 100, 'level' => 'ممتاز'];
    $this->actingAs($this->supervisor)->post(route('surah-tests.store'), array_merge($base, ['results' => [$row($this->indivB->id)]]))->assertSessionHas('error');
    $this->actingAs($this->supervisor)->post(route('surah-tests.store'), array_merge($base, ['results' => [$row($this->indivA->id), $row($this->indivB->id)]]))->assertSessionHas('error');
    expect(SurahTest::count())->toBe(0);
    $this->actingAs($this->supervisor)->post(route('surah-tests.store'), array_merge($base, ['results' => [$row($this->indivA->id)]]))->assertSessionHas('success');
    expect(SurahTest::count())->toBe(1);
});

test('future dates and duplicated result students are validation errors', function () {
    $this->actingAs($this->supervisor)->post(route('surah-tests.store'), array_merge($this->payload, ['test_date' => '2027-11-20']))
        ->assertSessionHasErrors('test_date');
    $row = ['student_id' => $this->student->id, 'prompt_errors' => 0, 'tashkeel_errors' => 0, 'percentage' => 100, 'level' => 'ممتاز'];
    $this->actingAs($this->supervisor)->post(route('surah-tests.store'), array_merge($this->payload, ['results' => [$row, $row]]))
        ->assertSessionHasErrors('results');
    expect(SurahTest::count())->toBe(0);
});
