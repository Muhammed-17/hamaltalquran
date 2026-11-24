<?php
use App\Models\Badges\BadgeType;
use App\Models\{Branch, Center, Circle, Student, Teacher, User};
use App\Services\Badges\{BadgeBalanceService, BadgeCollectionService, BadgeDistributionService, BadgeEntitlementService, BadgeStockService, BadgeTeacherHandoverService};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\ValidationException;

/**
 * إصلاحات فحص إنشاء التحصيل (تقرير 217): اعتماد جلسة فارغة كان يحرق شهر
 * الحلقة بلا تحصيل، ومسودة قديمة كانت تقبل تسويات بعد اكتمال الشهر
 * وتحجز كميات — الاعتماد صار يرفض الفارغة ويُلغي المسودات الشقيقة،
 * وإيجاد الجلسة يعيد فحص قاعدة الشهر حتى للمسودة القائمة.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\BadgePermissionSeeder::class);
    $mk = function (string $role, string $email) {
        $u = User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('Test123!'), 'status' => 'active']);
        $u->assignRole($role);
        return $u;
    };
    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    $this->circle = Circle::create(['name' => 'حلقة', 'type' => 'group', 'level' => 'build', 'branch_id' => $branch->id]);
    $this->admin = $mk('admin', 'admin@fix.local');
    $this->sup = $mk('supervisor', 'sup@fix.local');
    $supT = Teacher::create(['user_id' => $this->sup->id, 'branch_id' => $branch->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $branch->id, 'teacher_id' => $supT->id]);
    $this->manager = $mk('manager', 'mgr@fix.local');
    $this->teacherUser = $mk('teacher', 't@fix.local');
    $t = Teacher::create(['user_id' => $this->teacherUser->id, 'branch_id' => $branch->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circle->id, 'teacher_id' => $t->id, 'role' => 'main']);
    $this->student = Student::create(['name' => 'طالب', 'center_id' => $center->id, 'circle_id' => $this->circle->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
    $this->type = BadgeType::create(['name' => 'وسام', 'is_active' => true]);
    $this->bal = app(BadgeBalanceService::class);
    $this->coll = app(BadgeCollectionService::class);
    app(BadgeStockService::class)->recordMovement($this->type, 100, $this->admin);
    app(BadgeTeacherHandoverService::class)->handover($this->teacherUser, $this->type, 40, '2026-11-01', $this->admin);
    $e = app(BadgeEntitlementService::class)->create([
        'student_id' => $this->student->id, 'circle_id' => $this->circle->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 10, 'entitlement_date' => '2026-11-02',
    ], $this->admin);
    app(BadgeDistributionService::class)->distribute($e, 10, '2026-11-03', $this->teacherUser);
});

test('an empty session cannot be completed or burn the month', function () {
    $round = $this->coll->findOrCreateSessionRound($this->circle, $this->sup->id, '2026-12-05', $this->sup);
    expect(fn () => $this->coll->complete($round, $this->sup))->toThrow(ValidationException::class);
    expect($round->fresh()->status->value)->toBe('draft');

    // والشهر لم يحترق: تسوية حقيقية ثم اعتماد يعملان
    $this->coll->settleStudent($round, $this->student->id, [$this->type->id => ['good' => 3, 'damaged' => 0, 'owed' => 0]], $this->sup);
    $this->coll->complete($round, $this->sup);
    expect($round->fresh()->status->value)->toBe('completed');
});

test('completing one draft cancels sibling drafts and blocks their later settles', function () {
    $a = $this->coll->findOrCreateSessionRound($this->circle, $this->sup->id, '2026-12-05', $this->sup);
    $this->coll->settleStudent($a, $this->student->id, [$this->type->id => ['good' => 2, 'damaged' => 0, 'owed' => 0]], $this->sup);
    $b = $this->coll->findOrCreateSessionRound($this->circle, $this->manager->id, '2026-12-06', $this->manager);
    $this->coll->settleStudent($b, $this->student->id, [$this->type->id => ['good' => 1, 'damaged' => 0, 'owed' => 0]], $this->manager);

    $supBefore = $this->bal->teacherBalance($this->sup->id, $this->type->id);
    $this->coll->complete($a, $this->sup);

    // المسودة الشقيقة أُلغيت تلقائيًا (تحرير حجزها وخصوماتها)
    expect($b->fresh()->status->value)->toBe('cancelled')
        ->and($this->bal->teacherBalance($this->sup->id, $this->type->id) - $supBefore)->toBe(2);

    // وأي محاولة تسوية جديدة لنفس الحلقة والشهر مرفوضة بقاعدة الشهر
    expect(fn () => $this->coll->findOrCreateSessionRound($this->circle, $this->manager->id, '2026-12-06', $this->manager))
        ->toThrow(ValidationException::class);
});
