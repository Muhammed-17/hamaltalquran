<?php

use App\Models\{Branch, Center, Circle, Competition, Student, User};
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(\Database\Seeders\BadgePermissionSeeder::class);
    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    $circle = Circle::create(['name' => 'حلقة', 'type' => 'group', 'level' => 'build', 'branch_id' => $branch->id]);
    Center::create(['name' => 'مركز ثانٍ']);
    $individualCircle = Circle::create(['name' => 'حلقة فردية', 'type' => 'individual', 'level' => 'build', 'branch_id' => $branch->id]);
    $this->admin = User::create(['name' => 'مدير', 'email' => 'admin@search.local', 'password' => Hash::make('Test123!'), 'status' => 'active']);
    $this->admin->assignRole('admin');
    $this->student = Student::create(['name' => 'طالب', 'center_id' => $center->id, 'circle_id' => $circle->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
    $this->individualStudent = Student::create(['name' => 'طالب فردي', 'center_id' => $center->id, 'circle_id' => $individualCircle->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
    $this->competition = Competition::create(['name' => 'مسابقة', 'status' => 'active']);
});

test('converted pages render the searchable select for their entity fields', function () {
    $pages = [
        [route('badges.entitlements.index'), ['teacher_id', 'circle_id']],
        [route('badges.entitlements.statistics'), ['teacher_id', 'circle_id', 'badge_type_id']],
        [route('badges.entitlements.statistics-students'), ['teacher_id', 'circle_id', 'badge_type_id']],
        [route('badges.entitlements.statistics-distributors'), ['teacher_id', 'circle_id', 'badge_type_id']],
        [route('badges.collections.index'), ['circle_id']],
        [route('badges.shortages.index'), ['teacher_id', 'circle_id', 'badge_type_id']],
        [route('badges.stock.index'), ['badge_type_id', 'teacher_id']],
        [route('badges.stock.history'), ['badge_type_id']],
        [route('badges.handovers.index'), ['teacher_id', 'badge_type_id']],
        [route('badges.handovers.statistics'), ['teacher_id', 'badge_type_id']],
        [route('badges.handovers.create'), ['teacher_id']],
        [route('badges.distributions.index'), ['badge_type_id']],
        [route('students.index'), ['center_id']],
        [route('guardians.index'), ['center_id']],
        [route('guardians.create'), ['center_id']],
        [route('group-session-plans.index'), ['circle_id']],
        [route('group-session-plans.create'), ['circle_id']],
        [route('branches.create'), ['center_id']],
        [route('examiners.create'), ['center_id']],
        [route('student-construction-details.create', ['student_id' => $this->individualStudent->id]), ['current_surah_id']],
        [route('competitions.participants', $this->competition), ['level_id', 'center_id']],
        [route('dashboard'), ['center_id']],
    ];

    foreach ($pages as [$url, $fields]) {
        $r = $this->actingAs($this->admin)->get($url);
        $r->assertOk();
        foreach ($fields as $field) {
            $r->assertSee("searchableSelect('{$field}'", false);
        }
    }
});

test('entitlements index self-return section renders a card per held type (REPORT 147 replaced the select form with cards)', function () {
    $teacherUser = User::create(['name' => 'معلم', 'email' => 'teacher@search.local', 'password' => Hash::make('Test123!'), 'status' => 'active']);
    $teacherUser->assignRole('teacher');
    $type = \App\Models\Badges\BadgeType::create(['name' => 'وسام', 'is_active' => true]);
    app(\App\Services\Badges\BadgeStockService::class)->recordMovement($type, 10, $this->admin);
    app(\App\Services\Badges\BadgeTeacherHandoverService::class)->handoverMultiple($teacherUser, [$type->id => 4], now()->toDateString(), $this->admin);

    $r = $this->actingAs($teacherUser)->get(route('badges.entitlements.index'));
    $r->assertOk()->assertSee('وسام')->assertSee('في عهدتك')
        ->assertSee('name="badge_type_id" value="' . $type->id . '"', false);
    expect(substr_count($r->getContent(), route('badges.stock.returns.store')))->toBe(1);
});
