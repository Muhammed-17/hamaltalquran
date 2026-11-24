<?php
use App\Models\{Branch, Center, Circle, Student, Teacher, User};
use Database\Seeders\PermissionSeeder;

/**
 * فحص إنشاء الطالب (تقرير 215): ستة أخطاء مؤكدة بالاختبار الاختراقي —
 * تسرّب رسائل الاستثناء الخام (فيها SQL وبصمة كلمة السر)، تجاوز قاعدة اسم
 * ولي الأمر بحذف المفتاح، ولي أمر بلا email_verified_at، إخفاء 403/422
 * داخل 500، مركز لا يطابق مركز الحلقة، وحلقة لطالب غير بناء.
 */
function studentCreatePayload(array $overrides = []): array
{
    return array_merge([
        'supervisor_id' => $GLOBALS['sup']->id,
        'join_date' => '2026-11-01', 'applicant' => 'الأب',
        'name' => 'طالب جديد', 'gender' => 'ذكر', 'date_of_birth' => '2016-05-01',
        'address' => 'عنوان', 'center_id' => $GLOBALS['centerA']->id,
        'educational_stage' => 'ابتدائي', 'education_type' => 'عام', 'school_grade' => 'الأول',
        'previous_school' => 'مدرسة', 'health_status' => 'سليم', 'learning_difficulties' => 'لا يوجد',
        'personal_traits' => 'هادئ', 'student_exit_status' => 'مستمر',
        'reading' => 'جيد', 'center_entry_level' => 'construction',
        'study_system' => 'group', 'circle_id' => $GLOBALS['circleA']->id,
        'status' => 'مقيد', 'decision' => 'مقبول',
        'subscription_fees' => '100', 'received_tools' => 'مصحف',
        'guardian_id' => 'none',
    ], $overrides);
}

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $mk = function (string $role, string $tag) {
        $u = User::create(['name' => $tag, 'email' => $tag . '@t.test', 'password' => bcrypt('secret1'), 'status' => 'active']);
        $u->assignRole($role);
        return $u;
    };
    $this->admin = $mk('admin', 'الأدمن');
    $this->teacherUser = $mk('teacher', 'المعلم');
    $GLOBALS['centerA'] = Center::create(['name' => 'مركز أ']);
    $centerB = Center::create(['name' => 'مركز ب']);
    $branchA = Branch::create(['name' => 'فرع أ', 'center_id' => $GLOBALS['centerA']->id]);
    $branchB = Branch::create(['name' => 'فرع ب', 'center_id' => $centerB->id]);
    $GLOBALS['circleA'] = Circle::create(['name' => 'حلقة أ', 'branch_id' => $branchA->id, 'type' => 'group', 'level' => 'build', 'status' => 'active']);
    $this->circleB = Circle::create(['name' => 'حلقة ب', 'branch_id' => $branchB->id, 'type' => 'group', 'level' => 'build', 'status' => 'active']);
    $this->centerB = $centerB;
    $GLOBALS['sup'] = Teacher::create(['user_id' => $this->teacherUser->id, 'branch_id' => $branchA->id, 'name' => 'المشرف']);
});

test('store errors never leak exception internals', function () {
    $res = $this->actingAs($this->admin)->postJson(route('students.store'), studentCreatePayload([
        'guardian_id' => 'new', 'guardian_name' => 'ولي جديد',
        'parent_email' => $this->teacherUser->email, 'password' => 'secret123',
    ]));
    $res->assertStatus(500);
    expect($res->getContent())->not->toContain('SQLSTATE')->not->toContain('$2y$')
        ->and(Student::count())->toBe(0);
});

test('guardian name is required even when the key is omitted', function () {
    $payload = studentCreatePayload(['guardian_id' => 'new', 'parent_email' => 'newg@t.test', 'password' => 'secret123']);
    unset($payload['guardian_name']); // المفتاح غائب كليًا — كانت القاعدة تُتخطى
    $this->actingAs($this->admin)->postJson(route('students.store'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('guardian_name');
    expect(Student::count())->toBe(0)->and(User::where('email', 'newg@t.test')->exists())->toBeFalse();
});

test('inline created guardian is email verified', function () {
    $this->actingAs($this->admin)->postJson(route('students.store'), studentCreatePayload([
        'guardian_id' => 'new', 'guardian_name' => 'ولي جديد', 'parent_email' => 'fresh@t.test', 'password' => 'secret123',
    ]))->assertOk();
    $g = User::where('email', 'fresh@t.test')->first();
    expect($g)->not->toBeNull()->and($g->email_verified_at)->not->toBeNull()
        ->and($g->hasRole('guardian'))->toBeTrue();
});

test('center must match the circle center', function () {
    $this->actingAs($this->admin)->postJson(route('students.store'), studentCreatePayload([
        'center_id' => $GLOBALS['centerA']->id, 'circle_id' => $this->circleB->id,
    ]))->assertUnprocessable();
    expect(Student::count())->toBe(0);
});

test('non construction students cannot carry a circle', function () {
    $this->actingAs($this->admin)->postJson(route('students.store'), studentCreatePayload([
        'center_entry_level' => 'mastery', 'study_system' => null,
        'previous_memorization_side' => 'جهة', 'previous_khatamat_count' => '1',
        'current_review_amount' => 'جزء', 'self_evaluation' => 8, 'tajweed_matn' => 'التحفة',
        'desired_path' => 'حفظ', 'preferred_time_mastery' => 'مساء',
    ]))->assertUnprocessable()->assertJsonValidationErrors('circle_id');
    expect(Student::count())->toBe(0);
});

test('valid construction creation still works end to end', function () {
    $this->actingAs($this->admin)->postJson(route('students.store'), studentCreatePayload())
        ->assertOk()->assertJsonPath('success', true);
    $student = Student::first();
    expect($student)->not->toBeNull()
        ->and($student->circle_id)->toBe($GLOBALS['circleA']->id)
        ->and(\App\Models\CircleAssignmentHistory::where('student_id', $student->id)->count())->toBe(1);
});
