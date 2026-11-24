<?php

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Examiner;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/*
|--------------------------------------------------------------------------
| فحص شامل لكل دور — مصفوفة الصفحات والقدرات
|--------------------------------------------------------------------------
| لكل دور من الأدوار السبعة: الصفحات التي يجب أن تُفتح له (200)
| والصفحات التي يجب أن تُحجب عنه، مطابقةً لخريطة PermissionSeeder
| وللبوابات الفعلية في الراوتس والـ Policies.
|
| ملحوظتان موثقتان كما هي دون تغيير (قرار المالك):
| - مدير الفرع (manager) يملك جولات التحصيل وإنشاء/تعديل الطلاب،
|   بينما المدير العام (general_manager) لا يملكها — وله بدلًا منها
|   التقارير والتصدير والمستخدمون.
| - الممتحِن لا يملك صلاحية edit profile كباقي الأدوار.
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $mk = function (string $role) {
        $u = User::factory()->create(['status' => 'active']);
        $u->assignRole($role);
        return $u;
    };

    $this->center = Center::create(['name' => 'مركز الفحص']);
    $this->center2 = Center::create(['name' => 'مركز آخر']);
    $branch = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع الفحص']);
    $branch2 = Branch::create(['center_id' => $this->center2->id, 'name' => 'فرع آخر']);
    $this->circle = Circle::create(['branch_id' => $branch->id, 'name' => 'حلقة الفحص', 'type' => 'بنين', 'level' => 'مبتدئ']);
    $circle2 = Circle::create(['branch_id' => $branch2->id, 'name' => 'حلقة أخرى', 'type' => 'بنين', 'level' => 'مبتدئ']);

    $this->student = Student::create([
        'name' => 'طالب المركز الأول', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $this->circle->id, 'center_id' => $this->center->id,
    ]);
    $this->student2 = Student::create([
        'name' => 'طالب المركز الثاني', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $circle2->id, 'center_id' => $this->center2->id,
    ]);

    $this->users = [];
    $this->users['admin'] = $mk('admin');
    $this->users['general_manager'] = $mk('general_manager');

    $this->users['manager'] = $mk('manager');
    $mgrT = Teacher::create(['user_id' => $this->users['manager']->id]);
    $mgrT->branch()->associate($branch)->save();

    $this->users['supervisor'] = $mk('supervisor');
    $supT = Teacher::create(['user_id' => $this->users['supervisor']->id]);
    $supT->branch()->associate($branch)->save();
    $branch->supervisors()->attach($supT->id);

    $this->users['teacher'] = $mk('teacher');
    $teaT = Teacher::create(['user_id' => $this->users['teacher']->id]);
    $teaT->branch()->associate($branch)->save();
    $teaT->circles()->attach($this->circle->id, ['role' => 'main']);

    $this->users['guardian'] = $mk('guardian');
    Student::create([
        'name' => 'ابن الولي', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $this->circle->id, 'center_id' => $this->center->id,
        'guardian_id' => $this->users['guardian']->id,
    ]);

    $this->users['examiner'] = $mk('examiner');
    Examiner::create(['user_id' => $this->users['examiner']->id]);

    // الصفحات المسموحة لكل دور — خلاف ذلك محجوب
    $this->allowed = [
        'admin'           => ['/dashboard', '/students', '/teachers', '/circles', '/branches', '/centers', '/attendance', '/attendance/report', '/subscriptions', '/subscription-prices', '/competitions', '/surah-tests/individual', '/collection-rounds', '/examiners', '/admin/roles', '/profile'],
        'general_manager' => ['/students', '/circles', '/attendance', '/subscriptions', '/competitions', '/surah-tests/individual', '/profile'], // /attendance/report و/teachers أصبحتا أدمن فقط (تحويل الصلاحيات لأدوار)
        'manager'         => ['/students', '/circles', '/attendance', '/subscriptions', '/competitions', '/surah-tests/individual', '/collection-rounds', '/profile'],
        'supervisor'      => ['/students', '/circles', '/attendance', '/subscriptions', '/competitions', '/surah-tests/individual', '/collection-rounds', '/profile'],
        'teacher'         => ['/students', '/circles', '/attendance', '/subscriptions', '/profile'],
        'guardian'        => ['/guardian/my-dashboard'], // /profile أُغلق عليه بقرار تقرير 210 (حذف edit profile منه)
        'examiner'        => ['/examiner/dashboard'],
    ];
    $this->allPages = ['/dashboard', '/students', '/teachers', '/circles', '/branches', '/centers', '/attendance', '/attendance/report', '/subscriptions', '/subscription-prices', '/competitions', '/surah-tests/individual', '/collection-rounds', '/examiners', '/admin/roles', '/guardian/my-dashboard', '/examiner/dashboard', '/profile'];
});

test('each role can open exactly its allowed pages', function () {
    foreach ($this->allowed as $role => $pages) {
        foreach ($pages as $url) {
            $status = $this->actingAs($this->users[$role])->get($url)->status();
            expect($status)->toBe(200, "{$role} should open {$url}, got {$status}");
        }
    }
});

test('each role is denied every page outside its allowance', function () {
    foreach ($this->users as $role => $user) {
        foreach (array_diff($this->allPages, $this->allowed[$role]) as $url) {
            $status = $this->actingAs($user)->get($url)->status();
            expect($status)->not->toBe(200, "{$role} should NOT open {$url}");
        }
    }
});

test('manager student list is scoped to own center only', function () {
    $response = $this->actingAs($this->users['manager'])->get('/students');

    $response->assertOk();
    $ids = $response->viewData('students')->getCollection()->pluck('id');
    expect($ids)->toContain($this->student->id)
        ->and($ids)->not->toContain($this->student2->id);
});

test('students age filter works with portable age expression', function () {
    $young = (new Student)->forceFill([
        'name' => 'طالب صغير', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $this->circle->id, 'center_id' => $this->center->id,
        'date_of_birth' => now()->subYears(10)->format('Y-m-d'),
    ]);
    $young->save();

    $inRange = $this->actingAs($this->users['admin'])->get('/students?age_min=9&age_max=11');
    $inRange->assertOk();
    expect($inRange->viewData('students')->getCollection()->pluck('id'))->toContain($young->id);

    $outOfRange = $this->actingAs($this->users['admin'])->get('/students?age_min=20&age_max=30');
    $outOfRange->assertOk();
    expect($outOfRange->viewData('students')->getCollection()->pluck('id'))->not->toContain($young->id);
});