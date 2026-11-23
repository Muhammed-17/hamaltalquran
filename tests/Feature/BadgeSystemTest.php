<?php

/*
|--------------------------------------------------------------------------
| نظام إدارة الأوسمة — اختبارات شاملة (2026-11-21)
|--------------------------------------------------------------------------
| تغطي: المخزون الافتتاحي والإضافات والتسويات، التسليم للمعلمين وحدوده،
| الاستحقاق دون رصيد + العجز التلقائي، التوزيع الجزئي وحدوده، استكمال
| العجز، الإرجاع للإدارة، التحصيل (كامل/جزئي/عدم/فقدان/منع التكرار)،
| صحة أرصدة الإدارة والمعلم والطالب، الصلاحيات، ونطاق المراكز.
*/


use App\Models\Badges\BadgeCollectionItem;
use App\Models\Badges\BadgeDistribution;
use App\Models\Badges\BadgeEntitlement;
use App\Models\Badges\BadgeShortage;
use App\Models\Badges\BadgeType;
use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Badges\BadgeBalanceService;
use App\Services\Badges\BadgeCollectionService;
use App\Services\Badges\BadgeDistributionService;
use App\Services\Badges\BadgeEntitlementService;
use App\Services\Badges\BadgeStockService;
use App\Services\Badges\BadgeTeacherHandoverService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->balances = app(BadgeBalanceService::class);
    $this->stock = app(BadgeStockService::class);
    $this->handoverService = app(BadgeTeacherHandoverService::class);
    $this->entitlementService = app(BadgeEntitlementService::class);
    $this->distributionService = app(BadgeDistributionService::class);
    $this->collectionService = app(BadgeCollectionService::class);

    $this->centerA = Center::create(['name' => 'مركز أ']);
    $this->centerB = Center::create(['name' => 'مركز ب']);
    $this->branchA = Branch::create(['center_id' => $this->centerA->id, 'name' => 'فرع أ']);
    $this->branchB = Branch::create(['center_id' => $this->centerB->id, 'name' => 'فرع ب']);
    $this->circleA = Circle::create(['name' => 'حلقة أ', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchA->id]);
    $this->circleB = Circle::create(['name' => 'حلقة ب', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchB->id]);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');

    $this->teacherUser = User::factory()->create(['status' => 'active']);
    $this->teacherUser->assignRole('teacher');
    $this->teacher = Teacher::create(['user_id' => $this->teacherUser->id, 'branch_id' => $this->branchA->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circleA->id, 'teacher_id' => $this->teacher->id, 'role' => 'main', 'created_at' => now(), 'updated_at' => now()]);

    $managerB = User::factory()->create(['status' => 'active']);
    $managerB->assignRole('manager');
    $this->managerB = $managerB;
    Teacher::create(['user_id' => $managerB->id, 'branch_id' => $this->branchB->id]);

    $this->student1 = Student::create(['name' => 'طالب أول', 'center_id' => $this->centerA->id, 'circle_id' => $this->circleA->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر', 'supervisor_id' => $this->teacher->id]);
    $this->student2 = Student::create(['name' => 'طالب ثانٍ', 'center_id' => $this->centerA->id, 'circle_id' => $this->circleA->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر', 'supervisor_id' => $this->teacher->id]);

    $this->type = BadgeType::create(['name' => 'وسام الحفظ', 'is_active' => true]);
});

/** مخزون 100 + تسليم 40 للمعلم */
function badgeSeedStockAndHandover($test): void
{
    $test->stock->recordMovement($test->type, 100, $test->admin);
    $test->handoverService->handover($test->teacherUser, $test->type, 40, '2026-11-01', $test->admin);
}

test('stock: initial stock, additions and adjustments drive the admin balance', function () {
    $this->stock->recordMovement($this->type, 100, $this->admin);
    expect($this->balances->adminBalance($this->type->id))->toBe(100);

    $this->stock->recordMovement($this->type, 50, $this->admin);
    expect($this->balances->adminBalance($this->type->id))->toBe(150);

    $this->stock->recordMovement($this->type, -20, $this->admin, 'تلف 20 وسامًا');
    expect($this->balances->adminBalance($this->type->id))->toBe(130);

    // تسوية بلا سبب موثق مرفوضة
    try {
        $this->stock->recordMovement($this->type, -5, $this->admin);
        $this->fail('adjustment without notes must fail');
    } catch (ValidationException) {
        expect(true)->toBeTrue();
    }

    // تسوية تجعل الرصيد سالبًا مرفوضة
    try {
        $this->stock->recordMovement($this->type, -999, $this->admin, 'سبب');
        $this->fail('negative balance adjustment must fail');
    } catch (ValidationException) {
        expect($this->balances->adminBalance($this->type->id))->toBe(130);
    }
});

test('handover: moves balance from admin to teacher and rejects over-stock', function () {
    $this->stock->recordMovement($this->type, 30, $this->admin);

    try {
        $this->handoverService->handover($this->teacherUser, $this->type, 31, '2026-11-01', $this->admin);
        $this->fail('over-stock handover must fail');
    } catch (ValidationException) {
        expect(true)->toBeTrue();
    }

    $this->handoverService->handover($this->teacherUser, $this->type, 25, '2026-11-01', $this->admin);
    expect($this->balances->adminBalance($this->type->id))->toBe(5)
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(25);
});

test('handover create: multiple types in one submission, atomic on over-balance', function () {
    $type2 = \App\Models\Badges\BadgeType::create(['name' => 'وسام التميز', 'is_active' => true]);
    $this->stock->recordMovement($this->type, 100, $this->admin);
    $this->stock->recordMovement($type2, 50, $this->admin);

    // تسليم نوعين بضغطة واحدة
    $this->actingAs($this->admin)->post(route('badges.handovers.store'), [
        'teacher_id' => $this->teacherUser->id,
        'quantities' => [$this->type->id => 30, $type2->id => 20],
        'handover_date' => '2026-11-15',
    ])->assertRedirect(route('badges.handovers.index'));

    expect(\App\Models\Badges\BadgeTeacherHandover::count())->toBe(2)
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(30)
        ->and($this->balances->teacherBalance($this->teacherUser->id, $type2->id))->toBe(20)
        ->and($this->balances->adminBalance($this->type->id))->toBe(70);

    // نوع يتجاوز رصيد الإدارة ⇒ لا يُسجَّل أي تسليم (ذرّي) والخطأ على صف النوع
    $this->actingAs($this->admin)->post(route('badges.handovers.store'), [
        'teacher_id' => $this->teacherUser->id,
        'quantities' => [$this->type->id => 10, $type2->id => 999],
        'handover_date' => '2026-11-16',
    ])->assertSessionHasErrors('quantities.' . $type2->id);
    expect(\App\Models\Badges\BadgeTeacherHandover::count())->toBe(2);

    // كل الكميات صفر ⇒ خطأ تحقق ولا تسليم
    $this->actingAs($this->admin)->post(route('badges.handovers.store'), [
        'teacher_id' => $this->teacherUser->id,
        'quantities' => [$this->type->id => 0, $type2->id => 0],
        'handover_date' => '2026-11-16',
    ])->assertSessionHasErrors('quantities');
    expect(\App\Models\Badges\BadgeTeacherHandover::count())->toBe(2);
});

test('custody chain: every rank hands over only from its own balance, downward and in scope', function () {
    // فاعلون إضافيون: مدير عام، مدير فرع أ، مشرف على فرع أ
    $gm = User::factory()->create(['status' => 'active']);
    $gm->assignRole('general_manager');

    $managerA = User::factory()->create(['status' => 'active']);
    $managerA->assignRole('manager');
    Teacher::create(['user_id' => $managerA->id, 'branch_id' => $this->branchA->id]);

    $supervisor = User::factory()->create(['status' => 'active']);
    $supervisor->assignRole('supervisor');
    $supervisorTeacher = Teacher::create(['user_id' => $supervisor->id, 'branch_id' => $this->branchA->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branchA->id, 'teacher_id' => $supervisorTeacher->id, 'created_at' => now(), 'updated_at' => now()]);

    $bal = fn (User $u) => $this->balances->teacherBalance($u->id, $this->type->id);

    $this->stock->recordMovement($this->type, 1000, $this->admin);

    // الأدمن ← مدير عام 400 (من المركزي)
    $this->handoverService->handover($gm, $this->type, 400, '2026-11-02', $this->admin);
    expect($this->balances->adminBalance($this->type->id))->toBe(600)
        ->and($bal($gm))->toBe(400);

    // مدير عام ← مدير فرع 150 (من عهدة المدير العام — المركزي لا يتغير)
    $this->handoverService->handover($managerA, $this->type, 150, '2026-11-03', $gm);
    expect($bal($gm))->toBe(250)
        ->and($bal($managerA))->toBe(150)
        ->and($this->balances->adminBalance($this->type->id))->toBe(600);

    // مدير فرع ← مشرف 60، مشرف ← معلم 25
    $this->handoverService->handover($supervisor, $this->type, 60, '2026-11-04', $managerA);
    $this->handoverService->handover($this->teacherUser, $this->type, 25, '2026-11-05', $supervisor);
    expect($bal($managerA))->toBe(90)
        ->and($bal($supervisor))->toBe(35)
        ->and($bal($this->teacherUser))->toBe(25)
        ->and($this->balances->adminBalance($this->type->id))->toBe(600);

    // مدير عام بلا رصيد لا يستطيع التسليم
    $gm2 = User::factory()->create(['status' => 'active']);
    $gm2->assignRole('general_manager');
    try {
        $this->handoverService->handover($this->teacherUser, $this->type, 1, '2026-11-06', $gm2);
        $this->fail('handover without custody balance must fail');
    } catch (ValidationException) {
        expect(true)->toBeTrue();
    }

    // تجاوز رصيد العهدة مرفوض
    try {
        $this->handoverService->handover($this->teacherUser, $this->type, 999, '2026-11-06', $supervisor);
        $this->fail('handover above custody balance must fail');
    } catch (ValidationException) {
        expect(true)->toBeTrue();
    }

    // رتبة أعلى كمستلم مرفوضة عبر النموذج (مشرف ← مدير فرع)
    $this->actingAs($supervisor)->post(route('badges.handovers.store'), [
        'teacher_id' => $managerA->id,
        'quantities' => [$this->type->id => 5],
        'handover_date' => '2026-11-07',
    ])->assertSessionHasErrors('teacher_id');

    // خارج النطاق مرفوض (مدير فرع أ ← مدير فرع ب: رتبة مساوية ومركز آخر)
    $this->actingAs($managerA)->post(route('badges.handovers.store'), [
        'teacher_id' => $this->managerB->id,
        'quantities' => [$this->type->id => 5],
        'handover_date' => '2026-11-07',
    ])->assertSessionHasErrors('teacher_id');

    // المعلم لا يملك إنشاء تسليم أصلًا (لا يُسجَّل أي تسليم)
    $handoversBefore = \App\Models\Badges\BadgeTeacherHandover::count();
    $this->actingAs($this->teacherUser)->post(route('badges.handovers.store'), [
        'teacher_id' => $this->student1->id,
        'quantities' => [$this->type->id => 5],
        'handover_date' => '2026-11-07',
    ]);
    expect(\App\Models\Badges\BadgeTeacherHandover::count())->toBe($handoversBefore);

    // مشرف يسلّم معلم حلقاته عبر النموذج بنجاح
    $this->actingAs($supervisor)->post(route('badges.handovers.store'), [
        'teacher_id' => $this->teacherUser->id,
        'quantities' => [$this->type->id => 5],
        'handover_date' => '2026-11-08',
    ])->assertRedirect(route('badges.handovers.index'));
    expect($bal($supervisor))->toBe(30)
        ->and($bal($this->teacherUser))->toBe(30);

    // المشرف يوزّع على طالب في حلقة يشرف عليها من عهدته
    $entSup = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $supervisor->id, 'badge_type_id' => $this->type->id,
        'quantity' => 5, 'entitlement_date' => '2026-11-09',
    ], $this->admin);
    $this->distributionService->distribute($entSup, 5, '2026-11-10', $supervisor);
    expect($bal($supervisor))->toBe(25)
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(5);

    // مدير الفرع يوزّع بنفسه: استحقاق باسمه عبر النموذج (مسؤول بحكم مركزه) ثم توزيع
    $this->actingAs($managerA)->post(route('badges.entitlements.store'), [
        'student_id' => $this->student2->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $managerA->id,
        'quantities' => [$this->type->id => 10], 'entitlement_date' => '2026-11-09',
    ])->assertSessionHasNoErrors();
    $entMgr = BadgeEntitlement::where('teacher_id', $managerA->id)->firstOrFail();
    $this->distributionService->distribute($entMgr, 10, '2026-11-10', $managerA);
    expect($bal($managerA))->toBe(80)
        ->and($this->balances->studentBalance($this->student2->id, $this->type->id))->toBe(10);

    // المشرف يرجّع من عهدته للإدارة فيزيد المركزي
    $this->stock->returnFromTeacher($supervisor, $this->type, 5, $supervisor);
    expect($bal($supervisor))->toBe(20)
        ->and($this->balances->adminBalance($this->type->id))->toBe(605);
});

test('entitlement without teacher balance is saved and opens a shortage automatically', function () {
    $entitlement = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 5, 'entitlement_date' => '2026-11-10',
    ], $this->teacherUser);

    expect($entitlement->status->value)->toBe('pending');

    $shortage = BadgeShortage::where('entitlement_id', $entitlement->id)->first();
    expect($shortage)->not->toBeNull()
        ->and($shortage->required_quantity)->toBe(5)
        ->and($shortage->delivered_quantity)->toBe(0)
        ->and($shortage->shortage_quantity)->toBe(5)
        ->and($shortage->status->value)->toBe('pending');

    // لا عجز مكرر لنفس الاستحقاق
    expect(BadgeShortage::where('entitlement_id', $entitlement->id)->count())->toBe(1);
});

test('shortage opens for any undelivered remainder even when the distributor has balance', function () {
    badgeSeedStockAndHandover($this); // المعلم معه 40 — رصيده يكفي

    // استحقاق 6 دون أي تسليم (كما يحدث عند عدم تفعيل التسليم الفوري)
    $e = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 6, 'entitlement_date' => '2026-11-10',
    ], $this->admin);

    $shortage = BadgeShortage::where('entitlement_id', $e->id)->first();
    expect($shortage)->not->toBeNull()
        ->and($shortage->shortage_quantity)->toBe(6)
        ->and($shortage->status->value)->toBe('pending');

    // يظهر في صفحة النواقص وصفحة تسليم المستحق تفتح له
    $index = $this->actingAs($this->admin)->get(route('badges.shortages.index'));
    expect($index->viewData('totalRecords'))->toBeGreaterThanOrEqual(1);
    $this->actingAs($this->admin)->get(route('badges.shortages.deliver', [
        'student_id' => $this->student1->id, 'teacher_id' => $this->teacherUser->id,
    ]))->assertOk();

    // تسليم جزئي 4: المتبقي 2 بحالة جزئي — والسجل لا يختفي
    $this->distributionService->distribute($e, 4, '2026-11-11', $this->teacherUser);
    $shortage->refresh();
    expect($shortage->delivered_quantity)->toBe(4)
        ->and($shortage->shortage_quantity)->toBe(2)
        ->and($shortage->status->value)->toBe('partial');
});

test('distribution: partial delivery updates entitlement and shortage, limits enforced', function () {
    badgeSeedStockAndHandover($this); // المعلم معه 40

    $entitlement = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 50, 'entitlement_date' => '2026-11-10',
    ], $this->admin);

    // الرصيد 40 < المستحق 50 ← عجز مفتوح
    $shortage = BadgeShortage::where('entitlement_id', $entitlement->id)->first();
    expect($shortage)->not->toBeNull();

    // توزيع أكبر من رصيد المعلم مرفوض
    try {
        $this->distributionService->distribute($entitlement, 41, '2026-11-11', $this->teacherUser);
        $this->fail('over-balance distribution must fail');
    } catch (ValidationException) {
        expect(true)->toBeTrue();
    }

    // توزيع جزئي 30
    $distribution = $this->distributionService->distribute($entitlement, 30, '2026-11-11', $this->teacherUser);
    expect($distribution->shortage_id)->toBe($shortage->id)
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(10)
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(30);

    $entitlement->refresh();
    $shortage->refresh();
    expect($entitlement->status->value)->toBe('partial')
        ->and($shortage->delivered_quantity)->toBe(30)
        ->and($shortage->shortage_quantity)->toBe(20)
        ->and($shortage->status->value)->toBe('partial');

    // توزيع أكبر من المتبقي من الاستحقاق مرفوض (المتبقي 20، الرصيد 10 — نرفع الرصيد أولًا)
    $this->handoverService->handover($this->teacherUser, $this->type, 30, '2026-11-12', $this->admin);
    try {
        $this->distributionService->distribute($entitlement, 21, '2026-11-12', $this->teacherUser);
        $this->fail('over-entitlement distribution must fail');
    } catch (ValidationException) {
        expect(true)->toBeTrue();
    }

    // استكمال العجز بالكامل
    $this->distributionService->distribute($entitlement, 20, '2026-11-12', $this->teacherUser);
    $entitlement->refresh();
    $shortage->refresh();
    expect($entitlement->status->value)->toBe('completed')
        ->and($shortage->status->value)->toBe('resolved')
        ->and($shortage->shortage_quantity)->toBe(0)
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(50);
});

test('distribution cancel restores teacher balance and reopens the shortage', function () {
    badgeSeedStockAndHandover($this);

    $entitlement = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 45, 'entitlement_date' => '2026-11-10',
    ], $this->admin);

    $distribution = $this->distributionService->distribute($entitlement, 40, '2026-11-11', $this->teacherUser);
    expect($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(0);

    $this->distributionService->cancel($distribution, $this->admin, 'تسليم بالخطأ');
    expect($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(40)
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(0);

    $shortage = BadgeShortage::where('entitlement_id', $entitlement->id)->first();
    expect($shortage->delivered_quantity)->toBe(0)
        ->and($shortage->shortage_quantity)->toBe(45)
        ->and($shortage->status->value)->toBe('pending')
        ->and($entitlement->fresh()->status->value)->toBe('pending');
});

test('return to admin: deducts teacher balance, adds to admin, rejects over-balance', function () {
    badgeSeedStockAndHandover($this); // إدارة 60، معلم 40

    try {
        $this->stock->returnFromTeacher($this->teacherUser, $this->type, 41, $this->admin);
        $this->fail('over-balance return must fail');
    } catch (ValidationException) {
        expect(true)->toBeTrue();
    }

    $this->stock->returnFromTeacher($this->teacherUser, $this->type, 15, $this->admin);
    expect($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(25)
        ->and($this->balances->adminBalance($this->type->id))->toBe(75);
});

test('handover cancel: restores balances and is blocked after distribution', function () {
    $this->stock->recordMovement($this->type, 100, $this->admin);
    $handover = $this->handoverService->handover($this->teacherUser, $this->type, 40, '2026-11-01', $this->admin);

    $entitlement = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 10, 'entitlement_date' => '2026-11-10',
    ], $this->admin);
    $this->distributionService->distribute($entitlement, 10, '2026-11-11', $this->teacherUser);

    // أُلغي بعد توزيع جزء من الكمية؟ الرصيد المتبقي 30 < 40 ← مرفوض
    try {
        $this->handoverService->cancel($handover, $this->admin);
        $this->fail('handover cancel after partial distribution must fail');
    } catch (ValidationException) {
        expect(true)->toBeTrue();
    }

    // تسليم ثانٍ لم يُوزع منه شيء: الإلغاء يعمل
    $handover2 = $this->handoverService->handover($this->teacherUser, $this->type, 20, '2026-11-02', $this->admin);
    $this->handoverService->cancel($handover2, $this->admin);
    expect($handover2->fresh()->status)->toBe('cancelled')
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(30)
        ->and($this->balances->adminBalance($this->type->id))->toBe(60);
});

test('collections create page: per-type totals once, numeric amounts build the round', function () {
    badgeSeedStockAndHandover($this);

    // مسؤول التحصيل إداري (مشرف) — المعلم لم يعد يُسند إليه التحصيل
    $supervisor = User::factory()->create(['status' => 'active']);
    $supervisor->assignRole('supervisor');
    Teacher::create(['user_id' => $supervisor->id, 'branch_id' => $this->branchA->id]);

    $mk = function ($student, int $qty, string $day) {
        $e = $this->entitlementService->create([
            'student_id' => $student->id, 'circle_id' => $this->circleA->id,
            'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
            'quantity' => $qty, 'entitlement_date' => '2026-11-10',
        ], $this->admin);

        return $this->distributionService->distribute($e, $qty, $day, $this->teacherUser);
    };
    $d1 = $mk($this->student1, 10, '2026-11-11');
    $d2 = $mk($this->student1, 5, '2026-11-12');
    $d3 = $mk($this->student2, 7, '2026-11-11');

    // صفحة الإنشاء: اختيار الطالب يُظهر النوع مرة واحدة بإجمالياته (مستحق 15/موزّع 15 للطالب الأول)
    $page = $this->actingAs($this->admin)->get(route('badges.collections.create', ['circle_id' => $this->circleA->id, 'student_id' => $this->student1->id]));
    $page->assertOk()
        ->assertSee('يُحصَّل من الطالب')
        ->assertSee('يُخصم من المستحق')
        ->assertSee('إجمالي المستحق')
        ->assertSee('حفظ تحصيل الطالب')
        ->assertSee('اعتماد التحصيل')
        // التأكيدات بحوارات SweetAlert الخاصة بالموقع لا بتأكيد المتصفح
        ->assertSee('data-swal-confirm', false)
        ->assertDontSee('return confirm(', false);
    $g1 = $page->viewData('breakdown')->firstWhere(fn ($g) => $g['student']->id === $this->student1->id);
    expect($g1['types'])->toHaveCount(1)
        ->and($g1['types'][0]['entitled'])->toBe(15)
        ->and($g1['types'][0]['delivered'])->toBe(15)
        ->and($g1['types'][0]['collectable'])->toBe(15)
        ->and($g1['types'][0]['owed'])->toBe(0);

    // حفظ تحصيل الطالب الأول: 12 سليمًا من مسلّمه (FIFO: 10 من الأول + 2 من الثاني)
    $this->actingAs($this->admin)->post(route('badges.collections.settle-student'), [
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-11-20', 'student_id' => $this->student1->id,
        'amounts' => [$this->type->id => ['good' => 12, 'damaged' => 0, 'owed' => 0]],
    ])->assertRedirect();
    $round = \App\Models\Badges\BadgeCollectionRound::latest('id')->first();
    expect($round->status->value)->toBe('draft');
    $item1 = $round->items()->where('distribution_id', $d1->id)->first();
    $item2 = $round->items()->where('distribution_id', $d2->id)->first();
    expect($item1->collected_quantity)->toBe(10)
        ->and($item1->distributed_quantity)->toBe(10)
        ->and($item2->collected_quantity)->toBe(2)
        ->and($item2->distributed_quantity)->toBe(5);

    // حفظ الطالب الثاني في نفس الجلسة (نفس الجولة المسودة — لا جولة جديدة)
    $this->actingAs($this->admin)->post(route('badges.collections.settle-student'), [
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-11-20', 'student_id' => $this->student2->id,
        'amounts' => [$this->type->id => ['good' => 7, 'damaged' => 0, 'owed' => 0]],
    ])->assertRedirect();
    expect(\App\Models\Badges\BadgeCollectionRound::count())->toBe(1);
    $item3 = $round->items()->where('distribution_id', $d3->id)->first();
    expect($item3->collected_quantity)->toBe(7);

    // تجاوز ما في اليد مرفوض، وبيانات الطالب المحفوظة لا تتغير
    $this->actingAs($this->admin)->post(route('badges.collections.settle-student'), [
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-11-20', 'student_id' => $this->student1->id,
        'amounts' => [$this->type->id => ['good' => 99, 'damaged' => 0, 'owed' => 0]],
    ])->assertSessionHasErrors('amounts');
    expect($round->items()->where('distribution_id', $d1->id)->first()->collected_quantity)->toBe(10);

    // من قائمة الجولات: المسودة تُفتح في صفحة التحصيل (جلستها) للتصحيح، لا في صفحة العرض
    $index = $this->actingAs($this->admin)->get(route('badges.collections.index'));
    $index->assertOk()
        ->assertSee(route('badges.collections.create', ['circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id, 'collection_date' => '2026-11-20']))
        ->assertDontSee(route('badges.collections.show', $round));
});

test('collections create: owed deduction (game exchange) inside the round, cancel reverses it', function () {
    badgeSeedStockAndHandover($this);

    // مسؤول التحصيل إداري (مشرف) — المعلم لم يعد يُسند إليه التحصيل
    $supervisor = User::factory()->create(['status' => 'active']);
    $supervisor->assignRole('supervisor');
    Teacher::create(['user_id' => $supervisor->id, 'branch_id' => $this->branchA->id]);

    // استحقاق 50 سُلّم منه 7: في اليد 7 ومستحق متبقٍّ 43 (عجز مفتوح)
    $e = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 50, 'entitlement_date' => '2026-11-10',
    ], $this->admin);
    $this->distributionService->distribute($e, 7, '2026-11-11', $this->teacherUser);

    $g1 = $this->actingAs($this->admin)->get(route('badges.collections.create', ['circle_id' => $this->circleA->id]))
        ->viewData('breakdown')->firstWhere(fn ($g) => $g['student']->id === $this->student1->id);
    expect($g1['types'][0]['entitled'])->toBe(50)
        ->and($g1['types'][0]['delivered'])->toBe(7)
        ->and($g1['types'][0]['collectable'])->toBe(7)
        ->and($g1['types'][0]['owed'])->toBe(43);

    // تسوية الطالب في الجلسة: تحصيل 3 سليمًا من المسلّم + خصم 10 من المستحق
    $this->actingAs($this->admin)->post(route('badges.collections.settle-student'), [
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-11-30', 'student_id' => $this->student1->id,
        'amounts' => [$this->type->id => ['good' => 3, 'damaged' => 0, 'owed' => 10]],
    ])->assertRedirect();
    $round = \App\Models\Badges\BadgeCollectionRound::latest('id')->first();

    $redemption = \App\Models\Badges\BadgeRedemption::where('collection_round_id', $round->id)->first();
    expect($redemption)->not->toBeNull()
        ->and($redemption->totalShortage())->toBe(10)
        ->and($redemption->totalConsumed())->toBe(10)
        ->and($e->shortage->fresh()->shortage_quantity)->toBe(33) // 43 − 10
        ->and($e->shortage->fresh()->status->value)->toBe('partial')
        ->and($e->fresh()->distributableRemaining())->toBe(33)
        ->and($this->balances->adminBalance($this->type->id))->toBe(60); // لا قطع مادية في الخصم

    // صفحة الجولة تعرض الخصم صفًا واحدًا للطالب والنوع
    $show = $this->actingAs($this->admin)->get(route('badges.collections.show', $round));
    $show->assertOk()->assertSee('مستحق مُخصم بالاستبدال بلعبة ضمن هذه الجولة');
    expect($show->viewData('owedDeductions'))->toHaveCount(1)
        ->and($show->viewData('owedDeductions')[0]['qty'])->toBe(10);

    // اعتماد الجولة: رصيد الطالب ينقص بالمحصّل فقط (3)، والخصم ثابت
    $this->collectionService->complete($round, $this->teacherUser);
    expect($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(4)
        ->and($e->shortage->fresh()->shortage_quantity)->toBe(33);

    // إحصائيات الشهر: المتبقي = 50 − 7 − 10 = 33
    $stats = $this->actingAs($this->admin)->get(route('badges.entitlements.statistics', ['month' => '2026-11']));
    expect($stats->viewData('totals')['remaining'])->toBe(33);

    // جلسة ثانية بخصم 5 ثم إلغاؤها وهي مسودة: الخصم يُعكَس ويعود العجز 33
    $this->actingAs($this->admin)->post(route('badges.collections.settle-student'), [
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-12-01', 'student_id' => $this->student1->id,
        'amounts' => [$this->type->id => ['good' => 0, 'damaged' => 0, 'owed' => 5]],
    ])->assertRedirect();
    $round2 = \App\Models\Badges\BadgeCollectionRound::latest('id')->first();
    expect($e->shortage->fresh()->shortage_quantity)->toBe(28);
    $this->collectionService->cancel($round2, $this->teacherUser);
    expect($e->shortage->fresh()->shortage_quantity)->toBe(33)
        ->and(\App\Models\Badges\BadgeRedemption::where('collection_round_id', $round2->id)->first()->status->value)->toBe('cancelled');
});

test('collections create: collector list is management only (no admin, no teacher), supervisor can run a round', function () {
    badgeSeedStockAndHandover($this);

    $gm = User::factory()->create(['status' => 'active']);
    $gm->assignRole('general_manager');
    $supervisor = User::factory()->create(['status' => 'active']);
    $supervisor->assignRole('supervisor');
    $supTeacher = Teacher::create(['user_id' => $supervisor->id, 'branch_id' => $this->branchA->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branchA->id, 'teacher_id' => $supTeacher->id, 'created_at' => now(), 'updated_at' => now()]);

    // القائمة: المشرف والمدير العام — دون مدير النظام ودون أي معلم
    $page = $this->actingAs($this->admin)->get(route('badges.collections.create', ['circle_id' => $this->circleA->id]));
    $ids = $page->viewData('teachers')->pluck('id');
    expect($ids)->toContain($supervisor->id)
        ->toContain($gm->id)
        ->not->toContain($this->admin->id)
        ->not->toContain($this->teacherUser->id);

    // إسناد الجولة للمشرف يمرّ، واعتمادها يردّ المحصّل لعهدته هو
    $e = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 10, 'entitlement_date' => '2026-11-10',
    ], $this->admin);
    $this->distributionService->distribute($e, 10, '2026-11-11', $this->teacherUser);

    $this->actingAs($this->admin)->post(route('badges.collections.settle-student'), [
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-11-20', 'student_id' => $this->student1->id,
        'amounts' => [$this->type->id => ['good' => 10, 'damaged' => 0, 'owed' => 0]],
    ])->assertRedirect();
    $round = \App\Models\Badges\BadgeCollectionRound::latest('id')->first();
    expect($round->teacher_id)->toBe($supervisor->id);

    $this->collectionService->complete($round, $supervisor);
    expect($this->balances->teacherBalance($supervisor->id, $this->type->id))->toBe(10)
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(0);

    // إسناد التحصيل لحساب مدير النظام مرفوض
    $this->actingAs($this->admin)->post(route('badges.collections.settle-student'), [
        'circle_id' => $this->circleA->id, 'teacher_id' => $this->admin->id,
        'collection_date' => '2026-11-21', 'student_id' => $this->student1->id,
        'amounts' => [],
    ])->assertSessionHasErrors('teacher_id');

    // وإسناده لمعلم مرفوض أيضًا — التحصيل إداري فقط
    $this->actingAs($this->admin)->post(route('badges.collections.settle-student'), [
        'circle_id' => $this->circleA->id, 'teacher_id' => $this->teacherUser->id,
        'collection_date' => '2026-11-21', 'student_id' => $this->student1->id,
        'amounts' => [],
    ])->assertSessionHasErrors('teacher_id');
});

test('collections are management-only: teacher blocked, supervisor collects any circle from his account', function () {
    badgeSeedStockAndHandover($this);

    $supervisor = User::factory()->create(['status' => 'active']);
    $supervisor->assignRole('supervisor');
    $supTeacher = Teacher::create(['user_id' => $supervisor->id, 'branch_id' => $this->branchA->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branchA->id, 'teacher_id' => $supTeacher->id, 'created_at' => now(), 'updated_at' => now()]);

    $managerB = User::factory()->create(['status' => 'active']);
    $managerB->assignRole('manager');
    Teacher::create(['user_id' => $managerB->id, 'branch_id' => $this->branchB->id]);

    // المعلم لا يرى صفحات التحصيل أصلًا (رفض الوصول = تحويل أو 403)
    expect($this->actingAs($this->teacherUser)->get(route('badges.collections.index'))->status())->toBeIn([302, 403])
        ->and($this->actingAs($this->teacherUser)->get(route('badges.collections.create'))->status())->toBeIn([302, 403]);

    // المشرف يرى كل الحلقات في كل المراكز من حسابه
    $page = $this->actingAs($supervisor)->get(route('badges.collections.create'));
    expect($page->viewData('circles')->pluck('id'))->toContain($this->circleA->id)->toContain($this->circleB->id);

    // توزيع في حلقة بمركز آخر، ثم المشرف يحصّله بنفسه من حسابه
    $this->handoverService->handover($managerB, $this->type, 20, '2026-11-05', $this->admin);
    $studentB = Student::create(['name' => 'طالب ب', 'center_id' => $this->centerB->id, 'circle_id' => $this->circleB->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر', 'supervisor_id' => $this->teacher->id]);
    $e = $this->entitlementService->create([
        'student_id' => $studentB->id, 'circle_id' => $this->circleB->id,
        'teacher_id' => $managerB->id, 'badge_type_id' => $this->type->id,
        'quantity' => 8, 'entitlement_date' => '2026-11-10',
    ], $this->admin);
    $this->distributionService->distribute($e, 8, '2026-11-11', $managerB);

    $this->actingAs($supervisor)->post(route('badges.collections.settle-student'), [
        'circle_id' => $this->circleB->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-11-20', 'student_id' => $studentB->id,
        'amounts' => [$this->type->id => ['good' => 8, 'damaged' => 0, 'owed' => 0]],
    ])->assertRedirect();
    $round = \App\Models\Badges\BadgeCollectionRound::latest('id')->first();
    expect($round->teacher_id)->toBe($supervisor->id)->and($round->circle_id)->toBe($this->circleB->id);

    $this->actingAs($supervisor)->post(route('badges.collections.complete', $round))->assertRedirect();
    expect($this->balances->teacherBalance($supervisor->id, $this->type->id))->toBe(8)
        ->and($this->balances->studentBalance($studentB->id, $this->type->id))->toBe(0);
});

test('collection settle: damaged and missing are one category, outcome fixed at save time', function () {
    badgeSeedStockAndHandover($this);

    $supervisor = User::factory()->create(['status' => 'active']);
    $supervisor->assignRole('supervisor');
    Teacher::create(['user_id' => $supervisor->id, 'branch_id' => $this->branchA->id]);

    // توزيعان من نفس النوع لنفس الطالب (5 + 5) من استحقاقين منفصلين
    foreach ([5, 5] as $qty) {
        $e = $this->entitlementService->create([
            'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
            'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
            'quantity' => $qty, 'entitlement_date' => '2026-11-10',
        ], $this->admin);
        $this->distributionService->distribute($e, $qty, '2026-11-11', $this->teacherUser);
    }

    // تسوية من صفحة الإنشاء: 7 سليم + 2 تالف/مفقود — تُوزَّع على التوزيعات من الأقدم
    $this->actingAs($this->admin)->post(route('badges.collections.settle-student'), [
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-11-20', 'student_id' => $this->student1->id,
        'amounts' => [$this->type->id => ['good' => 7, 'damaged' => 2, 'owed' => 0]],
    ])->assertRedirect();
    $round = \App\Models\Badges\BadgeCollectionRound::latest('id')->first();
    $items = $round->items()->whereNotNull('badge_type_id')->orderBy('id')->get();
    expect($items)->toHaveCount(2)
        ->and($items[0]->collected_quantity)->toBe(5)
        ->and($items[0]->status->value)->toBe('collected')
        ->and($items[1]->collected_quantity)->toBe(4)
        ->and($items[1]->status->value)->toBe('damaged');

    // صفحة الجولة: النوع يظهر مرة واحدة رغم تعدد التوزيعات
    $html = $this->actingAs($this->admin)->get(route('badges.collections.show', $round))->assertOk()->getContent();
    expect(substr_count($html, $this->type->name))->toBe(1);

    // إعادة التسوية داخل نفس الجلسة تستبدل البيانات: 9 سليم + 1 تالف/مفقود
    $this->actingAs($this->admin)->post(route('badges.collections.settle-student'), [
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-11-20', 'student_id' => $this->student1->id,
        'amounts' => [$this->type->id => ['good' => 9, 'damaged' => 1, 'owed' => 0]],
    ])->assertRedirect();
    $items = $round->items()->whereNotNull('badge_type_id')->orderBy('id')->get();
    expect($items)->toHaveCount(2)
        ->and($items[0]->collected_quantity)->toBe(5)
        ->and($items[0]->status->value)->toBe('collected')
        ->and($items[1]->collected_quantity)->toBe(5)
        ->and($items[1]->status->value)->toBe('damaged');

    // الاعتماد: السليم فقط (5) يعود لعهدة المسؤول، والطالب يُسوّى بالكامل (10)
    $this->collectionService->complete($round, $supervisor);
    expect($this->balances->teacherBalance($supervisor->id, $this->type->id))->toBe(5)
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(0);
});

test('collection: all students included, partial/missing tracked, no double collection', function () {
    badgeSeedStockAndHandover($this);

    $entitlement = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 10, 'entitlement_date' => '2026-11-10',
    ], $this->admin);
    $distribution = $this->distributionService->distribute($entitlement, 10, '2026-11-11', $this->teacherUser);

    $round = $this->collectionService->createRound($this->circleA, $this->teacherUser->id, '2026-11-20', $this->teacherUser);

    // كل طلاب الحلقة موجودون: بند للتوزيع + صف صفري للطالب الثاني
    $items = $round->items()->get();
    expect($items)->toHaveCount(2);
    $item = $items->firstWhere('distribution_id', $distribution->id);
    $zeroItem = $items->firstWhere('student_id', $this->student2->id);
    expect($item->distributed_quantity)->toBe(10)
        ->and($zeroItem)->not->toBeNull()
        ->and($zeroItem->distributed_quantity)->toBe(0);

    // تحصيل أكبر من الموزع مرفوض
    try {
        $this->collectionService->updateItem($item, 11, null, null, $this->teacherUser);
        $this->fail('over-collection must fail');
    } catch (ValidationException) {
        expect(true)->toBeTrue();
    }

    // تحصيل جزئي 6 — قبل الاعتماد لا يتغير رصيد الطالب
    $this->collectionService->updateItem($item, 6, null, null, $this->teacherUser);
    expect($item->fresh()->status->value)->toBe('partial')
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(10);

    $this->collectionService->complete($round, $this->teacherUser);
    expect($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(4)
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(30 + 6);

    // جولة ثانية في شهر لاحق (قاعدة الشهر: جولة واحدة معتمدة للحلقة في الشهر): بند التوزيع نفسه يظهر بالمتبقي فقط (4) — لا تحصيل مكرر
    $round2 = $this->collectionService->createRound($this->circleA, $this->teacherUser->id, '2026-12-02', $this->teacherUser);
    $item2 = $round2->items()->where('distribution_id', $distribution->id)->first();
    expect($item2->distributed_quantity)->toBe(4);

    // تسجيل الفقدان للمتبقي ثم الاعتماد
    $this->collectionService->updateItem($item2, 0, 'missing', 'فقدها الطالب', $this->teacherUser);
    $this->collectionService->complete($round2, $this->teacherUser);
    expect($item2->fresh()->status->value)->toBe('missing')
        ->and($this->balances->totalMissing($this->type->id))->toBe(4)
        // المفقود لا يعود لعهدة المعلم ولا يُخصم من رصيد الطالب كمرتجع
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(36)
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(4);
});

test('collection damaged: settled for the student but never returns to teacher custody', function () {
    badgeSeedStockAndHandover($this); // إدارة 60، معلم 40

    $entitlement = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 10, 'entitlement_date' => '2026-11-10',
    ], $this->admin);
    $distribution = $this->distributionService->distribute($entitlement, 10, '2026-11-11', $this->teacherUser);
    expect($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(30);

    $round = $this->collectionService->createRound($this->circleA, $this->teacherUser->id, '2026-11-20', $this->teacherUser);
    $item = $round->items()->where('distribution_id', $distribution->id)->first();

    // تالف بعدد صفر مرفوض (التالف رجع فعليًا)
    try {
        $this->collectionService->updateItem($item, 0, 'damaged', null, $this->teacherUser);
        $this->fail('damaged with zero returned must fail');
    } catch (ValidationException) {
        expect(true)->toBeTrue();
    }

    // رجع 4 تالفين من أصل 10
    $this->collectionService->updateItem($item, 4, 'damaged', 'ممزقة', $this->teacherUser);
    $this->collectionService->complete($round, $this->teacherUser);

    expect($item->fresh()->status->value)->toBe('damaged')
        // الطالب سُوّي حسابه في الـ 4 (رجعهم فعليًا)
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(6)
        // المعلم لم يُضف لعهدته شيء من التالف
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(30)
        ->and($this->balances->totalDamaged($this->type->id))->toBe(4);

    // الجولة التالية: المتبقي القابل للتحصيل 6 فقط
    $round2 = $this->collectionService->createRound($this->circleA, $this->teacherUser->id, '2026-11-25', $this->teacherUser);
    $item2 = $round2->items()->where('distribution_id', $distribution->id)->first();
    expect($item2->distributed_quantity)->toBe(6);
});

test('permissions and policies gate badge operations', function () {
    // الأنواع والتسليمات بوابات أدوار لا صلاحيات: السياسات تتحقق من الدور
    expect($this->admin->can('create', \App\Models\Badges\BadgeType::class))->toBeTrue()
        ->and($this->teacherUser->can('create', \App\Models\Badges\BadgeType::class))->toBeFalse()
        ->and($this->teacherUser->can('badges.entitlements.create'))->toBeTrue()
        ->and($this->teacherUser->can('create', \App\Models\Badges\BadgeTeacherHandover::class))->toBeFalse()
        ->and($this->managerB->can('create', \App\Models\Badges\BadgeTeacherHandover::class))->toBeTrue()
        ->and($this->teacherUser->can('create', \App\Models\Badges\BadgeCollectionRound::class))->toBeFalse();

    // المعلم لا يفتح صفحة المخزون المركزي
    $this->actingAs($this->teacherUser)->get(route('badges.stock.index'))->assertRedirect();

    // ولا ينشئ نوع وسام
    $this->actingAs($this->teacherUser)->post(route('badges.types.store'), ['name' => 'وسام ممنوع']);
    expect(BadgeType::where('name', 'وسام ممنوع')->exists())->toBeFalse();
});

test('center scope: foreign manager cannot entitle or view another center circle', function () {
    // محاولة إنشاء استحقاق لحلقة مركز آخر عبر HTTP
    $this->actingAs($this->managerB)->post(route('badges.entitlements.store'), [
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id,
        'quantities' => [$this->type->id => 3], 'entitlement_date' => '2026-11-10',
    ])->assertSessionHasErrors('circle_id');
    expect(BadgeEntitlement::count())->toBe(0);

    // وطالب من حلقة أخرى مرفوض حتى للمعلم نفسه
    $studentB = Student::create(['name' => 'طالب ب', 'center_id' => $this->centerB->id, 'circle_id' => $this->circleB->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر', 'supervisor_id' => $this->teacher->id]);
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.store'), [
        'student_id' => $studentB->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id,
        'quantities' => [$this->type->id => 3], 'entitlement_date' => '2026-11-10',
    ])->assertSessionHasErrors('student_id');
    expect(BadgeEntitlement::count())->toBe(0);

    // إنشاء سليم عبر HTTP يعمل ويفتح العجز (لا رصيد)
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.store'), [
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id,
        'quantities' => [$this->type->id => 3], 'entitlement_date' => '2026-11-10',
    ])->assertSessionHasNoErrors();
    expect(BadgeEntitlement::count())->toBe(1)
        ->and(BadgeShortage::count())->toBe(1);
});

test('handovers: month filter and Excel report', function () {
    badgeSeedStockAndHandover($this); // تسليم 40 من النوع الافتراضي بتاريخ 2026-11-01

    // فلتر الشهر على تاريخ التسليم
    $this->actingAs($this->admin)->get(route('badges.handovers.index', ['month' => '2026-11']))
        ->assertOk()->assertSee('2026-11-01');
    $this->actingAs($this->admin)->get(route('badges.handovers.index', ['month' => '2026-10']))
        ->assertOk()->assertDontSee('2026-11-01');

    // زر التقرير: ملف Excel يُنزَّل حسب الفلاتر
    $this->actingAs($this->admin)->get(route('badges.handovers.report', ['month' => '2026-11']))
        ->assertOk()->assertDownload();

    // ملف التقرير: قائمة تسليمات، وبدل عمود النوع عمودٌ لكل نوع فيه الكمية
    $export = new \App\Exports\BadgeHandoversReportExport(
        fn () => \App\Models\Badges\BadgeTeacherHandover::query()
    );
    [$detail, $summary] = $export->sheets();
    $headings = $detail->headings();
    $typeCol = array_search($this->type->name, $headings);
    expect($typeCol)->not->toBeFalse()
        ->and($headings[0])->toBe('الشهر')
        ->and($headings[1])->toBe('المستلم')
        ->and($headings[count($headings) - 2])->toBe('الإجمالي')
        ->and($headings)->not->toContain('المسلّم')
        ->and($headings)->not->toContain('تاريخ التسليم')
        ->and($headings)->not->toContain('الحالة');
    // تسليم ثانٍ لنفس المستلم في نفس الشهر من نوع آخر: يجب أن يندمج في نفس الصف
    $type2 = BadgeType::create(['name' => 'وسام التميز', 'is_active' => true]);
    $this->stock->recordMovement($type2, 50, $this->admin);
    $this->handoverService->handover($this->teacherUser, $type2, 15, '2026-11-20', $this->admin);

    $export = new \App\Exports\BadgeHandoversReportExport(
        fn () => \App\Models\Badges\BadgeTeacherHandover::query()
    );
    [$detail, $summary] = $export->sheets();
    $headings = $detail->headings();
    $typeCol = array_search($this->type->name, $headings);
    $type2Col = array_search('وسام التميز', $headings);
    $rows = $detail->collection();
    expect($rows)->toHaveCount(1); // صف واحد للمستلم — اسمه يظهر مرة واحدة، وبلا صف إجمالي أخير
    $row = $rows->first();
    expect($row[0])->toBe('2026-11')
        ->and($row[1])->toBe($this->teacherUser->name)
        ->and($row[$typeCol])->toBe(40)
        ->and($row[$type2Col])->toBe(15)
        ->and($row[count($row) - 2])->toBe(55); // إجمالي الصف عبر الأنواع
    $summaryRows = $summary->collection();
    expect($summaryRows->last()['type'])->toBe('الإجمالي العام')
        ->and($summaryRows->last()['quantity'])->toBe(55);
});

test('badge pages render for authorized users', function () {
    badgeSeedStockAndHandover($this);

    // صفحة /badges (لوحة الأوسمة) محذوفة نهائيًا — الإحصائيات خاصة بالاستحقاقات
    $this->actingAs($this->admin)->get('/badges')->assertNotFound();
    $this->actingAs($this->admin)->get(route('badges.entitlements.statistics'))->assertOk();
    $this->actingAs($this->admin)->get(route('badges.types.index'))->assertOk();
    // حقل صورة الوسام محذوف نهائيًا — نموذج النوع بلا أي رفع ملفات
    $this->actingAs($this->admin)->get(route('badges.types.create'))
        ->assertOk()->assertDontSee('name="image"', false);
    $this->actingAs($this->admin)->get(route('badges.stock.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('badges.stock.history'))->assertOk();
    $this->actingAs($this->admin)->get(route('badges.handovers.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('badges.handovers.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('badges.entitlements.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('badges.entitlements.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('badges.distributions.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('badges.shortages.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('badges.collections.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('badges.collections.create'))->assertOk();
    // وصفحة التقارير المستقلة محذوفة أيضًا — التقرير صار ملفًا يُنزَّل من صفحة الاستحقاقات
    $this->actingAs($this->admin)->get('/badges/reports')->assertNotFound();
    // وصفحة سجل العمليات محذوفة أيضًا — السجل يُكتب داخليًا فقط دون واجهة
    $this->actingAs($this->admin)->get('/badges/logs')->assertNotFound();

    // إحصائيات الاستحقاقات لمدير النظام فقط — حتى المعلم صاحب البيانات لا يفتحها
    $this->actingAs($this->teacherUser)->get(route('badges.entitlements.statistics'))->assertRedirect();
    $this->actingAs($this->teacherUser)->get(route('badges.entitlements.index'))->assertOk();
    $this->actingAs($this->teacherUser)->get(route('badges.shortages.index'))->assertOk();
});

// ============================================================
// الصفحة الموحدة: استحقاقات متعددة الأنواع + تسليم فوري حسب الرصيد
// ============================================================
test('unified entitlements: multi-type create with instant distribution by balance', function () {
    badgeSeedStockAndHandover($this); // المعلم معه 40 من النوع الأساسي فقط
    $type2 = BadgeType::create(['name' => 'وسام التميز', 'is_active' => true]);

    // ضغطة واحدة: نوعان — الأول بكمية أكبر من الرصيد، والثاني بلا رصيد أصلًا
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.store'), [
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id,
        'quantities' => [$this->type->id => 50, $type2->id => 7],
        'entitlement_date' => '2026-11-12', 'instant' => 1,
    ])->assertSessionHasNoErrors();

    // استحقاقان، وتوزيع واحد فقط (40 = كل رصيد النوع الأول)، وعجزا النوعين يعكسان المتبقي
    expect(BadgeEntitlement::count())->toBe(2)
        ->and(BadgeDistribution::count())->toBe(1)
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(40)
        ->and($this->balances->studentBalance($this->student1->id, $type2->id))->toBe(0)
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(0);
    $ent1 = BadgeEntitlement::where('badge_type_id', $this->type->id)->firstOrFail();
    expect($ent1->status->value)->toBe('partial')
        ->and($ent1->shortage?->shortage_quantity)->toBe(10);
    $ent2 = BadgeEntitlement::where('badge_type_id', $type2->id)->firstOrFail();
    expect($ent2->status->value)->toBe('pending')
        ->and($ent2->shortage?->shortage_quantity)->toBe(7);

    // نوعا العملية الواحدة يشتركان في معرّف دفعة واحد (يظهران مجموعة واحدة في القائمة)
    expect($ent1->batch_id)->not->toBeNull()
        ->and($ent2->batch_id)->toBe($ent1->batch_id);

    // بدون تسليم فوري: استحقاق فقط حتى مع توفر الرصيد لاحقًا
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.store'), [
        'student_id' => $this->student2->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id,
        'quantities' => [$type2->id => 3],
        'entitlement_date' => '2026-11-12',
    ])->assertSessionHasNoErrors();
    expect(BadgeEntitlement::count())->toBe(3)
        ->and(BadgeDistribution::count())->toBe(1);

    // والعملية المنفصلة لها دفعة مختلفة، وقائمة الاستحقاقات تعمل بالتجميع
    $ent3 = BadgeEntitlement::where('student_id', $this->student2->id)->firstOrFail();
    expect($ent3->batch_id)->not->toBe($ent1->batch_id);
    $this->actingAs($this->teacherUser)->get(route('badges.entitlements.index'))->assertOk();

    // استكمال «المتبقي» من القائمة (نفس مسار التوزيع) بعد وصول رصيد جديد للمعلم
    $this->handoverService->handover($this->teacherUser, $this->type, 10, '2026-11-13', $this->admin);
    $this->actingAs($this->teacherUser)->post(route('badges.distributions.store'), [
        'entitlement_id' => $ent1->id, 'quantity' => 10, 'distribution_date' => '2026-11-14',
    ])->assertSessionHasNoErrors();
    expect($ent1->fresh()->status->value)->toBe('completed')
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(50);

    // لا كميات موجبة = لا شيء يُسجَّل
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.store'), [
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id,
        'quantities' => [$this->type->id => 0],
        'entitlement_date' => '2026-11-12', 'instant' => 1,
    ])->assertSessionHasErrors('quantities');
    expect(BadgeEntitlement::count())->toBe(3);

    // فلاتر القائمة: المسؤول عن التوزيع + الشهر (تاريخ الاستحقاق)
    $this->actingAs($this->admin)->get(route('badges.entitlements.index', ['teacher_id' => $this->teacherUser->id, 'month' => '2026-11']))
        ->assertOk()->assertSee('طالب أول');
    $this->actingAs($this->admin)->get(route('badges.entitlements.index', ['month' => '2026-10']))
        ->assertOk()->assertDontSee('طالب أول');
    $this->actingAs($this->admin)->get(route('badges.entitlements.index', ['teacher_id' => $this->admin->id]))
        ->assertOk()->assertDontSee('طالب أول');

    // إحصائيات الاستحقاقات حسب الفلاتر: إجمالي المستحق 60 والموزّع 50 في نوفمبر، وصفر خارجه
    $this->actingAs($this->admin)->get(route('badges.entitlements.statistics', ['month' => '2026-11']))
        ->assertOk()->assertSee('>60<', false)->assertSee('>50<', false);
    $this->actingAs($this->admin)->get(route('badges.entitlements.statistics', ['month' => '2026-10']))
        ->assertOk()->assertDontSee('>60<', false);
    $this->actingAs($this->admin)->get(route('badges.entitlements.statistics', ['teacher_id' => $this->admin->id]))
        ->assertOk()->assertDontSee('>60<', false);

    // زر التقرير: ملف Excel يُنزَّل حسب الفلاتر
    $this->actingAs($this->admin)->get(route('badges.entitlements.report', ['month' => '2026-11']))
        ->assertOk()->assertDownload();
});

// ============================================================
// ملف التقرير: إجمالي كل نوع + صف الإجمالي العام
// ============================================================

test('report export shows per-type totals and a grand total row', function () {
    badgeSeedStockAndHandover($this); // المعلم معه 40 من النوع الافتراضي

    $type2 = BadgeType::create(['name' => 'وسام التميز', 'is_active' => true]);
    $this->stock->recordMovement($type2, 50, $this->admin);
    $this->handoverService->handover($this->teacherUser, $type2, 50, '2026-11-02', $this->admin);

    // استحقاقان: 30 من النوع الأول (توزيع فوري كامل) و20 من الثاني (10 موزّعة و10 متبقية)
    $this->entitlementService->createMultiple([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'entitlement_date' => '2026-11-05',
    ], [$this->type->id => 30, $type2->id => 10], true, $this->admin);
    $this->entitlementService->create([
        'student_id' => $this->student2->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $type2->id,
        'quantity' => 10, 'entitlement_date' => '2026-11-06',
    ], $this->teacherUser);

    $export = new \App\Exports\BadgeEntitlementsReportExport(
        fn () => \App\Models\Badges\BadgeEntitlement::query()
    );
    [$detail, $summary] = $export->sheets();

    // ورقة الملخص: صف لكل نوع بإجماليه، ثم صف الإجمالي العام
    $summaryRows = $summary->collection();
    expect($summaryRows)->toHaveCount(3);
    $byType = $summaryRows->keyBy('type');
    expect($byType[$this->type->name]['entitled'])->toBe(30)
        ->and($byType[$this->type->name]['delivered'])->toBe(30)
        ->and($byType[$this->type->name]['remaining'])->toBe(0)
        ->and($byType['وسام التميز']['entitled'])->toBe(20)
        ->and($byType['وسام التميز']['delivered'])->toBe(10)
        ->and($byType['وسام التميز']['remaining'])->toBe(10);
    $grand = $summaryRows->last();
    expect($grand['type'])->toBe('الإجمالي العام')
        ->and($grand['entitled'])->toBe(50)
        ->and($grand['delivered'])->toBe(40)
        ->and($grand['remaining'])->toBe(10);

    // الورقة الأولى بنفس تصميم ملف المستخدم النموذجي: صف واحد لكل طالب، واسم
    // النوع مدموج فوق «المستحق/المستلم/الإجمالي» وإجمالي النوع صيغة SUM حية
    $detailRows = $detail->collection();
    expect($detailRows)->toHaveCount(3); // طالبان + صف الإجمالي — لا صفوف مكررة لنفس الطالب
    [$headRow1, $headRow2] = $detail->headings();
    $base1 = array_search($this->type->name, $headRow1);
    $base2 = array_search('وسام التميز', $headRow1);
    expect($base1)->not->toBeFalse()->and($base2)->not->toBeFalse()
        ->and($headRow2[$base1])->toBe('المستحق')->and($headRow2[$base1 + 1])->toBe('المستلم')
        ->and($headRow2[$base1 + 2])->toBe('الإجمالي')
        ->and($headRow1[count($headRow1) - 3])->toBe('إجمالي المستحق')
        ->and($headRow1[count($headRow1) - 2])->toBe('إجمالي الموزّع')
        ->and($headRow1[count($headRow1) - 1])->toBe('إجمالي المتبقي');
    $letter = fn ($zeroBased) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($zeroBased + 1);
    $byName = $detailRows->keyBy(0);
    $first = $byName['طالب أول']; // الصف 3 في الملف
    expect($first[$base1])->toBe(0)->and($first[$base1 + 1])->toBe(30)   // الحفظ: استلم 30 ولم يتبقَّ شيء
        ->and($first[$base1 + 2])->toBe('=SUM(' . $letter($base1) . '3:' . $letter($base1 + 1) . '3)')
        ->and($first[$base2])->toBe(0)->and($first[$base2 + 1])->toBe(10) // التميز: استلم 10
        ->and($first[count($first) - 3])->toBe('=' . implode('+', collect([$base1, $base2])->sort()->map(fn ($b) => $letter($b) . '3')->all()))  // إجمالي المستحق
        ->and($first[count($first) - 2])->toBe('=' . implode('+', collect([$base1, $base2])->sort()->map(fn ($b) => $letter($b + 1) . '3')->all())); // إجمالي الموزّع
    $second = $byName['طالب ثانٍ']; // الصف 4
    expect($second[$base2])->toBe(10)->and($second[$base2 + 1])->toBe(0) // له 10 مستحقة لم يستلمها
        ->and($second[$base2 + 2])->toBe('=SUM(' . $letter($base2) . '4:' . $letter($base2 + 1) . '4)');
    $grand = $detailRows->last();
    expect($grand[0])->toBe('الإجمالي')
        ->and($grand[$base1])->toBe('=SUM(' . $letter($base1) . '3:' . $letter($base1) . '4)')
        ->and($grand[$base1 + 1])->toBe('=SUM(' . $letter($base1 + 1) . '3:' . $letter($base1 + 1) . '4)');
});


test('handovers index: one operation with several types shows as a single grouped row', function () {
    $type2 = BadgeType::create(['name' => 'وسام التميز', 'is_active' => true]);
    $this->stock->recordMovement($this->type, 100, $this->admin);
    $this->stock->recordMovement($type2, 50, $this->admin);

    $handovers = $this->handoverService->handoverMultiple($this->teacherUser, [
        $this->type->id => 10,
        $type2->id => 15,
    ], '2026-11-10', $this->admin);

    // العملية الواحدة = batch_id واحد مشترك بين كل سجلاتها
    expect($handovers)->toHaveCount(2)
        ->and($handovers[0]->batch_id)->not->toBeNull()
        ->and($handovers[0]->batch_id)->toBe($handovers[1]->batch_id);

    // والقائمة تعرضها صفًّا واحدًا بإجمالي العملية، لا صفًّا لكل نوع
    $this->actingAs($this->admin)
        ->get(route('badges.handovers.index'))
        ->assertOk()
        ->assertSee('وسام الحفظ')
        ->assertSee('وسام التميز')
        ->assertSee('الإجمالي: 25');
});

test('handovers statistics page: totals follow the filters and count operations by batch', function () {
    $type2 = BadgeType::create(['name' => 'وسام التميز', 'is_active' => true]);
    $this->stock->recordMovement($this->type, 100, $this->admin);
    $this->stock->recordMovement($type2, 50, $this->admin);
    $this->handoverService->handoverMultiple($this->teacherUser, [
        $this->type->id => 10,
        $type2->id => 15,
    ], '2026-11-10', $this->admin);
    $cancelled = $this->handoverService->handover($this->teacherUser, $this->type, 5, '2026-11-11', $this->admin);
    $this->handoverService->cancel($cancelled, $this->admin);

    $response = $this->actingAs($this->admin)->get(route('badges.handovers.statistics'));
    $response->assertOk();
    $totals = $response->viewData('totals');
    expect($totals['received'])->toBe(25)          // النشط فقط: 10 + 15
        ->and($totals['operations'])->toBe(1)      // العملية المتعددة تُعدّ واحدة
        ->and($totals['recipients'])->toBe(1)
        ->and($totals['cancelled_qty'])->toBe(5)
        ->and($totals['cancelled_records'])->toBe(1);
    $perType = $response->viewData('perType');
    expect($perType[$this->type->id]['qty'])->toBe(10)
        ->and($perType[$type2->id]['qty'])->toBe(15);

    // جدول «حسب المستلم»: كمية كل نوع على حدة بجانب الإجمالي
    $perRecipient = $response->viewData('perRecipient');
    expect($perRecipient[$this->teacherUser->id]['types'][$this->type->id])->toBe(10)
        ->and($perRecipient[$this->teacherUser->id]['types'][$type2->id])->toBe(15)
        ->and($perRecipient[$this->teacherUser->id]['qty'])->toBe(25);
    $response->assertSee('<th class="px-5 py-3 text-sm font-bold text-gray-600">وسام التميز</th>', false);

    // فلتر الشهر يضيّق الأرقام
    $nov = $this->actingAs($this->admin)->get(route('badges.handovers.statistics', ['month' => '2026-10']));
    expect($nov->viewData('totals')['received'])->toBe(0);
});

test('admin as distributor draws from central stock, not a personal custody', function () {
    $balances = app(BadgeBalanceService::class);
    $this->stock->recordMovement($this->type, 50, $this->admin);
    expect($balances->adminBalance($this->type->id))->toBe(50)
        ->and($balances->distributorBalance($this->admin->id, $this->type->id))->toBe(50);

    // استحقاق موزّعه الأدمن بقيمة 10: سجل المتبقّي يُفتح لأي غير مسلّم حتى مع
    // كفاية الرصيد المركزي، والتوزيع الكامل منه يُغلقه
    $e1 = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->admin->id, 'badge_type_id' => $this->type->id,
        'quantity' => 10, 'entitlement_date' => '2026-11-10',
    ], $this->admin);
    expect($e1->shortage?->shortage_quantity)->toBe(10);
    $this->distributionService->distribute($e1, 10, '2026-11-10', $this->admin);
    expect($balances->adminBalance($this->type->id))->toBe(40)
        ->and($e1->shortage->fresh()->status->value)->toBe('resolved');

    // استحقاق 45 والمتبقي مركزيًا 40: يُفتح عجز (الرصيد لا يغطي الكل) يعكس
    // كامل غير المسلّم — وصفحة النواقص لا تقول «لا رصيد عند الموزّع»
    $e2 = $this->entitlementService->create([
        'student_id' => $this->student2->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->admin->id, 'badge_type_id' => $this->type->id,
        'quantity' => 45, 'entitlement_date' => '2026-11-10',
    ], $this->admin);
    expect($e2->shortage?->shortage_quantity)->toBe(45);
    $this->actingAs($this->admin)->get(route('badges.shortages.index'))
        ->assertOk()->assertDontSee('لا رصيد عند الموزّع');

    // صفحة «تسليم المستحق» من زر النواقص: تُسلّم المتاح مركزيًا (40) ويبقى الناقص 5
    $this->actingAs($this->admin)->post(route('badges.shortages.deliver-store'), [
        'teacher_id' => $this->admin->id, 'student_id' => $this->student2->id,
        'quantities' => [$this->type->id => 40],
    ])->assertRedirect();
    expect($e2->shortage->fresh()->shortage_quantity)->toBe(5)
        ->and($balances->adminBalance($this->type->id))->toBe(0);

    // بعد إدخال مخزون جديد يُستكمل الباقي ويُغلق العجز
    $this->stock->recordMovement($this->type, 10, $this->admin);
    $this->actingAs($this->admin)->post(route('badges.shortages.deliver-store'), [
        'teacher_id' => $this->admin->id, 'student_id' => $this->student2->id,
        'quantities' => [$this->type->id => 5],
    ])->assertRedirect();
    expect($e2->shortage->fresh()->status->value)->toBe('resolved')
        ->and($balances->adminBalance($this->type->id))->toBe(5);
});

test('shortages index: grouped by distributor, one row per student, handover button prefilled with the shortage', function () {
    // بلا رصيد عند الموزّع: الطالب الأول عجزه في نوعين (10 + 4)، والثاني في نوع واحد (7)
    $type2 = BadgeType::create(['name' => 'وسام التميز', 'is_active' => true]);
    foreach ([
        [$this->student1->id, $this->type->id, 10],
        [$this->student1->id, $type2->id, 4],
        [$this->student2->id, $this->type->id, 7],
    ] as [$studentId, $typeId, $qty]) {
        $this->entitlementService->create([
            'student_id' => $studentId, 'circle_id' => $this->circleA->id,
            'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $typeId,
            'quantity' => $qty, 'entitlement_date' => '2026-11-10',
        ], $this->teacherUser);
    }
    expect(BadgeShortage::count())->toBe(3);

    $response = $this->actingAs($this->admin)->get(route('badges.shortages.index'));
    $response->assertOk()
        ->assertSee('عرض الطلاب')
        ->assertSee('إجمالي الناقص: 21')
        // لا فلتر حالة عجز في الصفحة (تُعرض المفتوحة فقط دائمًا)
        ->assertDontSee('حالة العجز')
        ->assertDontSee('name="status"', false)
        // ألوان البطاقة inline (تظهر حتى مع build قديم لا يعرف كلاسات الأحمر الجديدة)
        ->assertSee('background-color:#991b1b', false)
        ->assertSee('background-color:#b91c1c', false);
    // كل طالب يظهر مرة واحدة في الصفحة كلها (صف واحد مهما تعددت أنواع عجزه)
    expect(substr_count($response->getContent(), $this->student1->name))->toBe(1)
        ->and(substr_count($response->getContent(), $this->student2->name))->toBe(1);
    // موزّع واحد = مجموعة واحدة، وسجلات العجز الثلاثة داخلها
    $group = $response->viewData('groups')[0];
    expect($response->viewData('groups'))->toHaveCount(1)
        ->and($group['items'])->toHaveCount(3)
        ->and($response->viewData('totalRecords'))->toBe(3);

    // زر «تسليم» للأدمن (يستطيع التسليم للموزّع) ويعبّئ نموذج التسليم بكميات العجز
    expect($group['canHandover'])->toBeTrue();
    $create = $this->actingAs($this->admin)->get($group['handoverUrl']);
    $create->assertOk();
    expect($create->viewData('prefillTeacherId'))->toBe($this->teacherUser->id)
        ->and($create->viewData('prefillQuantities')[$this->type->id])->toBe(17) // 10 + 7
        ->and($create->viewData('prefillQuantities')[$type2->id])->toBe(4);
});

test('shortages index filters: month, distributor, circle, type and student search narrow the groups', function () {
    $type2 = BadgeType::create(['name' => 'وسام التميز', 'is_active' => true]);
    foreach ([
        [$this->student1->id, $this->type->id, 10],
        [$this->student1->id, $type2->id, 4],
        [$this->student2->id, $this->type->id, 7],
    ] as [$studentId, $typeId, $qty]) {
        $this->entitlementService->create([
            'student_id' => $studentId, 'circle_id' => $this->circleA->id,
            'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $typeId,
            'quantity' => $qty, 'entitlement_date' => '2026-11-10',
        ], $this->teacherUser);
    }
    expect(BadgeShortage::count())->toBe(3);

    $idx = fn (array $params = []) => $this->actingAs($this->admin)->get(route('badges.shortages.index', $params));

    // حقول الفلاتر موجودة وبلا فلتر حالة
    $idx()->assertOk()
        ->assertSee('name="month"', false)->assertSee('name="teacher_id"', false)
        ->assertSee('name="circle_id"', false)->assertSee('name="badge_type_id"', false)
        ->assertDontSee('name="status"', false);

    // نوع الوسام: سجل واحد فقط (عجز النوع الثاني)
    expect($idx(['badge_type_id' => $type2->id])->viewData('totalRecords'))->toBe(1);
    // الشهر على تاريخ الاستحقاق
    expect($idx(['month' => '2026-11'])->viewData('totalRecords'))->toBe(3)
        ->and($idx(['month' => '2026-10'])->viewData('totalRecords'))->toBe(0);
    // الموزّع والحلقة
    expect($idx(['teacher_id' => $this->teacherUser->id])->viewData('totalRecords'))->toBe(3)
        ->and($idx(['teacher_id' => $this->admin->id])->viewData('totalRecords'))->toBe(0)
        ->and($idx(['circle_id' => $this->circleA->id])->viewData('totalRecords'))->toBe(3)
        ->and($idx(['circle_id' => 999999])->viewData('totalRecords'))->toBe(0);
    // بحث باسم الطالب
    expect($idx(['q' => $this->student2->name])->viewData('totalRecords'))->toBe(1);
});

test('shortages visibility: each rank sees his own shortage and those below him only', function () {
    // مدير فرع أ + مشرف على حلقة أ (نفس تجهيز اختبار سلسلة العهدة)
    $managerA = User::factory()->create(['status' => 'active']);
    $managerA->assignRole('manager');
    Teacher::create(['user_id' => $managerA->id, 'branch_id' => $this->branchA->id]);
    $supervisor = User::factory()->create(['status' => 'active']);
    $supervisor->assignRole('supervisor');
    $supervisorTeacher = Teacher::create(['user_id' => $supervisor->id, 'branch_id' => $this->branchA->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branchA->id, 'teacher_id' => $supervisorTeacher->id, 'created_at' => now(), 'updated_at' => now()]);

    // عجز لكل واحد من الثلاثة (بلا أرصدة عند أيٍّ منهم)
    $mk = fn (User $distributor) => $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $distributor->id, 'badge_type_id' => $this->type->id,
        'quantity' => 3, 'entitlement_date' => '2026-11-10',
    ], $distributor);
    $teacherEnt = $mk($this->teacherUser);
    $supEnt = $mk($supervisor);
    $mgrEnt = $mk($managerA);
    expect(BadgeShortage::count())->toBe(3);

    $groupTeacherIds = fn ($response) => collect($response->viewData('groups'))
        ->map(fn ($g) => $g['teacher']?->id)->sort()->values()->all();

    // المشرف: عجزه + عجز المعلم (تحته) — ولا يرى عجز المدير (فوقه)
    $supResponse = $this->actingAs($supervisor)->get(route('badges.shortages.index'));
    $supResponse->assertOk();
    expect($groupTeacherIds($supResponse))->toBe(collect([$supervisor->id, $this->teacherUser->id])->sort()->values()->all());

    // المعلم: عجزه فقط
    $teacherResponse = $this->actingAs($this->teacherUser)->get(route('badges.shortages.index'));
    expect($groupTeacherIds($teacherResponse))->toBe([$this->teacherUser->id]);

    // المدير: عجزه + المشرف + المعلم (كل من تحته في مركزه)
    $mgrResponse = $this->actingAs($managerA)->get(route('badges.shortages.index'));
    expect($groupTeacherIds($mgrResponse))->toBe(collect([$managerA->id, $supervisor->id, $this->teacherUser->id])->sort()->values()->all());

    // الأدمن: الكل
    $adminResponse = $this->actingAs($this->admin)->get(route('badges.shortages.index'));
    expect($groupTeacherIds($adminResponse))->toBe(collect([$managerA->id, $supervisor->id, $this->teacherUser->id])->sort()->values()->all());

    // سياسة العرض منسجمة: المشرف يفتح عجز المعلم ولا يفتح عجز المدير
    $teacherShortage = BadgeShortage::where('entitlement_id', $teacherEnt->id)->firstOrFail();
    $mgrShortage = BadgeShortage::where('entitlement_id', $mgrEnt->id)->firstOrFail();
    expect($supervisor->can('view', $teacherShortage))->toBeTrue()
        ->and($supervisor->can('view', $mgrShortage))->toBeFalse();
});

test('shortages: deliver page settles a student remainders per type up to the entered quantities and balance', function () {
    $type2 = BadgeType::create(['name' => 'وسام التميز', 'is_active' => true]);
    foreach ([
        [$this->student1->id, $this->type->id, 10],
        [$this->student1->id, $type2->id, 4],
        [$this->student2->id, $this->type->id, 7],
    ] as [$studentId, $typeId, $qty]) {
        $this->entitlementService->create([
            'student_id' => $studentId, 'circle_id' => $this->circleA->id,
            'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $typeId,
            'quantity' => $qty, 'entitlement_date' => '2026-11-10',
        ], $this->teacherUser);
    }
    // الموزّع يستلم 12 من النوع الأول فقط — النوع الثاني بلا رصيد
    $this->stock->recordMovement($this->type, 100, $this->admin);
    $this->handoverService->handover($this->teacherUser, $this->type, 12, '2026-11-11', $this->admin);

    // الزر في صف الطالب صار رابطًا لصفحة «تسليم المستحق»
    $this->actingAs($this->teacherUser)->get(route('badges.shortages.index'))
        ->assertOk()->assertSee('/badges/shortages/deliver?', false);

    // الصفحة تعرض لكل نوع: المتبقّي والرصيد والقابل للتسليم (10 من الأول، صفر من الثاني)
    $page = $this->actingAs($this->teacherUser)->get(route('badges.shortages.deliver', [
        'teacher_id' => $this->teacherUser->id, 'student_id' => $this->student1->id,
    ]))->assertOk()->assertSee('تسليم المستحق');
    $rows = collect($page->viewData('rows'))->keyBy(fn ($r) => $r['type']->id);
    expect($rows[$this->type->id]['remaining'])->toBe(10)
        ->and($rows[$this->type->id]['deliverable'])->toBe(10)
        ->and($rows[$type2->id]['remaining'])->toBe(4)
        ->and($rows[$type2->id]['deliverable'])->toBe(0);

    // تسليم الطالب الأول بالمقترح: 10 من النوع الأول، ويبقى عجز النوع الثاني كاملًا
    $this->actingAs($this->teacherUser)->post(route('badges.shortages.deliver-store'), [
        'teacher_id' => $this->teacherUser->id, 'student_id' => $this->student1->id,
        'quantities' => [$this->type->id => 10, $type2->id => 0],
    ])->assertRedirect()->assertSessionHas('success');
    expect($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(10)
        ->and($this->balances->studentBalance($this->student1->id, $type2->id))->toBe(0)
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(2);
    $s1t2 = BadgeShortage::where('student_id', $this->student1->id)->where('badge_type_id', $type2->id)->firstOrFail();
    expect($s1t2->shortage_quantity)->toBe(4);

    // الطالب الثاني: يُدخل 7 لكن الرصيد المتبقي 2 فقط — تسليم جزئي ويبقى عجز 5 مع تنبيه
    $this->actingAs($this->teacherUser)->post(route('badges.shortages.deliver-store'), [
        'teacher_id' => $this->teacherUser->id, 'student_id' => $this->student2->id,
        'quantities' => [$this->type->id => 7],
    ])->assertRedirect()->assertSessionHas('error');
    expect($this->balances->studentBalance($this->student2->id, $this->type->id))->toBe(2);
    $s2 = BadgeShortage::where('student_id', $this->student2->id)->firstOrFail();
    expect($s2->shortage_quantity)->toBe(5);

    // مدير مركز آخر لا يستطيع الاستكمال نيابة عنه: لا توزيع جديد
    $before = BadgeDistribution::count();
    $denied = $this->actingAs($this->managerB)->post(route('badges.shortages.deliver-store'), [
        'teacher_id' => $this->teacherUser->id, 'student_id' => $this->student2->id,
        'quantities' => [$this->type->id => 5],
    ]);
    expect($denied->status())->toBeIn([302, 403])
        ->and(BadgeDistribution::count())->toBe($before);
});

// ============================================================
// صفحة التسليم اليومي (تحل محل نموذج إنشاء الاستحقاق)
// ============================================================
test('daily sheet: records entitlements per student, instant delivery, and delta edits with a delivered floor', function () {
    badgeSeedStockAndHandover($this); // المعلم معه 40 من النوع الأساسي
    $ctx = ['date' => '2026-11-22', 'circle_id' => $this->circleA->id, 'teacher_id' => $this->teacherUser->id];

    // الصفحة تُعرض بقائمة الحلقة وسياقها
    $this->actingAs($this->teacherUser)->get(route('badges.entitlements.create', $ctx))
        ->assertOk()->assertSee('طالب أول')->assertSee('التسليم اليومي');

    // حفظ أول: طالبان معدّلان فقط (الثالث لا يصله صف فلا يُسجَّل له شيء)
    // والنجاح ينتقل لقائمة الاستحقاقات كما في حفظ الغياب
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'instant' => 1,
        'rows' => [
            $this->student1->id => [$this->type->id => 50],
            $this->student2->id => [$this->type->id => 5],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect(route('badges.entitlements.index'));
    expect(BadgeEntitlement::count())->toBe(2)
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(40)
        ->and($this->balances->studentBalance($this->student2->id, $this->type->id))->toBe(0);
    $ent1 = BadgeEntitlement::where('student_id', $this->student1->id)->firstOrFail();
    expect($ent1->shortage?->shortage_quantity)->toBe(10);
    $ent2 = BadgeEntitlement::where('student_id', $this->student2->id)->firstOrFail();
    expect($ent2->shortage?->shortage_quantity)->toBe(5)
        ->and($ent2->batch_id)->not->toBe($ent1->batch_id); // دفعة مستقلة لكل طالب

    // إعادة فتح نفس اليوم: الخانات تحمل إجمالي اليوم المسجل
    $view = $this->actingAs($this->teacherUser)->get(route('badges.entitlements.create', $ctx))->viewData('sheet');
    expect($view[$this->student1->id][$this->type->id]['today'])->toBe(50)
        ->and($view[$this->student1->id][$this->type->id]['remaining'])->toBe(10)
        ->and($view[$this->student1->id][$this->type->id]['delivered_today'])->toBe(40);

    // بلا أي تعديل: لا يُسجَّل شيء
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + ['rows' => []])
        ->assertSessionHas('success', 'لا توجد تعديلات — لم يُسجَّل شيء.');
    expect(BadgeEntitlement::count())->toBe(2);

    // زيادة للطالب الأول (+5 بلا رصيد: العجز يكبر) ونقصان للثاني (5 ← 3)
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'rows' => [$this->student1->id => [$this->type->id => 55]],
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'rows' => [$this->student2->id => [$this->type->id => 3]],
    ])->assertSessionHasNoErrors();
    // العجز موزّع على الاستحقاقين: القديم 10 كما هو، والزيادة استحقاق جديد بعجز 5
    $deltaEnt = BadgeEntitlement::where('student_id', $this->student1->id)->latest('id')->firstOrFail();
    expect($ent1->fresh()->shortage?->shortage_quantity)->toBe(10)
        ->and((int) $deltaEnt->quantity)->toBe(5)
        ->and($deltaEnt->shortage?->shortage_quantity)->toBe(5)
        ->and((int) $ent2->fresh()->quantity)->toBe(3)
        ->and($ent2->fresh()->shortage?->shortage_quantity)->toBe(3);

    // نقصان تحت حدّ المسلّم فعليًا (40): يُطبَّق حتى الحد ويُبلَّغ بالباقي في رسالة مرافقة
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'rows' => [$this->student1->id => [$this->type->id => 30]],
    ])->assertRedirect(route('badges.entitlements.index'))->assertSessionHas('error');
    $todayTotal = (int) BadgeEntitlement::where('student_id', $this->student1->id)
        ->whereDate('entitlement_date', '2026-11-22')->where('status', '!=', 'cancelled')->sum('quantity');
    expect($todayTotal)->toBe(40)
        ->and(session('error'))->toContain('بمقدار 10');
});

test('daily sheet: rows are served from the shared balance in the teacher edit order, not page order', function () {
    // رصيد الموزّع 10 فقط، وطالبان يطلب كل منهما 10
    $this->stock->recordMovement($this->type, 10, $this->admin);
    $this->handoverService->handover($this->teacherUser, $this->type, 10, '2026-11-21', $this->admin);
    $ctx = ['date' => '2026-11-22', 'circle_id' => $this->circleA->id, 'teacher_id' => $this->teacherUser->id];

    // ترتيب الصفحة: الأول قبل الثاني — وبلا ترتيب تعديل يُخدم الأول من الرصيد
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'instant' => 1,
        'rows' => [
            $this->student1->id => [$this->type->id => 10],
            $this->student2->id => [$this->type->id => 10],
        ],
    ])->assertSessionHasNoErrors();
    expect($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(10)
        ->and($this->balances->studentBalance($this->student2->id, $this->type->id))->toBe(0);

    // وفي يوم آخر بنفس الرصيد: ترتيب التعديل (الثاني أولًا) يقلب من يُخدم
    $this->stock->recordMovement($this->type, 10, $this->admin);
    $this->handoverService->handover($this->teacherUser, $this->type, 10, '2026-11-23', $this->admin);
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), [
        'date' => '2026-11-23', 'circle_id' => $this->circleA->id, 'teacher_id' => $this->teacherUser->id,
        'instant' => 1,
        'order' => $this->student2->id . ',' . $this->student1->id,
        'rows' => [
            $this->student1->id => [$this->type->id => 10],
            $this->student2->id => [$this->type->id => 10],
        ],
    ])->assertSessionHasNoErrors();
    // خُدم الثاني أولًا: التسليم سوّى دينه الأقدم (استحقاق اليوم الأول اكتمل)،
    // واستحقاقا اليوم الثاني لكليهما بقيا بعجز — بينما الأول لم يُخدم أصلًا في هذا اليوم
    $day1Ent2 = BadgeEntitlement::where('student_id', $this->student2->id)->whereDate('entitlement_date', '2026-11-22')->firstOrFail();
    $day2Ent1 = BadgeEntitlement::where('student_id', $this->student1->id)->whereDate('entitlement_date', '2026-11-23')->firstOrFail();
    $day2Ent2 = BadgeEntitlement::where('student_id', $this->student2->id)->whereDate('entitlement_date', '2026-11-23')->firstOrFail();
    expect($this->balances->studentBalance($this->student2->id, $this->type->id))->toBe(10)
        ->and($day1Ent2->status->value)->toBe('completed')
        ->and($day2Ent2->shortage?->shortage_quantity)->toBe(10)
        ->and($day2Ent1->shortage?->shortage_quantity)->toBe(10)
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(10); // رصيده التراكمي من اليوم الأول كما هو
});

test('daily sheet: instant delivery settles the oldest open remainder first', function () {
    // استحقاق أمس بلا رصيد (عجز 10)، واليوم وصلت عهدة جديدة للموزّع
    $old = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 10, 'entitlement_date' => '2026-11-20',
    ], $this->admin);
    expect($old->shortage?->shortage_quantity)->toBe(10);
    $this->stock->recordMovement($this->type, 10, $this->admin);
    $this->handoverService->handover($this->teacherUser, $this->type, 10, '2026-11-21', $this->admin);

    // تسجيل استحقاق جديد اليوم مع التسليم الفوري: يُسوّى العجز القديم أولًا
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), [
        'date' => '2026-11-22', 'circle_id' => $this->circleA->id, 'teacher_id' => $this->teacherUser->id,
        'instant' => 1,
        'rows' => [$this->student1->id => [$this->type->id => 3]],
    ])->assertSessionHasNoErrors();

    expect($old->fresh()->status->value)->toBe('completed')
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(10);
    $new = BadgeEntitlement::whereDate('entitlement_date', '2026-11-22')->firstOrFail();
    expect($new->status->value)->toBe('pending')
        ->and($new->shortage?->shortage_quantity)->toBe(3);
});

test('daily sheet cards: one badge per type per day, and the per-card instant button delivers in place', function () {
    $ctx = ['date' => '2026-11-22', 'circle_id' => $this->circleA->id, 'teacher_id' => $this->teacherUser->id];

    // البطاقات معروضة بخانة التسليم الفوري والأزرار المساعدة للتعليم الجماعي
    $this->actingAs($this->teacherUser)->get(route('badges.entitlements.create', $ctx))
        ->assertOk()->assertSee('data-student-card', false)->assertSee('تسليم فوري')
        ->assertSee('تفعيل كل الأوسمة')->assertSee('تفعيل التسليم الفوري للجميع');

    // تعليم الوسام (1) يسجّل استحقاق اليوم فقط — بلا أي تسليم
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'rows' => [$this->student1->id => [$this->type->id => 1]],
    ])->assertSessionHasNoErrors();
    $ent = BadgeEntitlement::where('student_id', $this->student1->id)->firstOrFail();
    expect((int) $ent->quantity)->toBe(1)
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(0);

    // إلغاء التعليم (0) يلغي استحقاق اليوم ما دام لم يُسلَّم
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'rows' => [$this->student1->id => [$this->type->id => 0]],
    ])->assertSessionHasNoErrors();
    expect($ent->fresh()->status->value)->toBe('cancelled');

    // استحقاق مسجّل من قبل + رصيد عند الموزّع: زر البطاقة يسلّم في مكانه ويبقى في الورقة
    $this->stock->recordMovement($this->type, 5, $this->admin);
    $this->handoverService->handover($this->teacherUser, $this->type, 5, '2026-11-21', $this->admin);
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'rows' => [$this->student1->id => [$this->type->id => 1]],
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'instant' => 1, 'stay' => 1,
        'rows' => [$this->student1->id => [$this->type->id => 1]],
    ])->assertRedirect(route('badges.entitlements.create', $ctx));
    expect($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(1)
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(4);
});

test('daily sheet: per-type instant delivers only that badge, and unchecking deletes a delivered entitlement for real', function () {
    $type2 = BadgeType::create(['name' => 'وسام التميز', 'is_active' => true]);
    $ctx = ['date' => '2026-11-22', 'circle_id' => $this->circleA->id, 'teacher_id' => $this->teacherUser->id];
    $this->stock->recordMovement($this->type, 100, $this->admin);
    $this->handoverService->handover($this->teacherUser, $this->type, 5, '2026-11-21', $this->admin);

    // متبقّيات قديمة للطالب الأول في النوعين
    $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 3, 'entitlement_date' => '2026-11-20',
    ], $this->admin);
    $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $type2->id,
        'quantity' => 2, 'entitlement_date' => '2026-11-20',
    ], $this->admin);

    // زر الوسام الأول وحده: يُسلَّم النوع الأول فقط، والثاني لا يُمسّ
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'instant' => 1, 'stay' => 1,
        'rows' => [$this->student1->id => [$this->type->id => 0]],
    ])->assertSessionHasNoErrors();
    expect($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(3)
        ->and($this->balances->studentBalance($this->student1->id, $type2->id))->toBe(0)
        ->and(BadgeDistribution::count())->toBe(1);

    // الطالب الثاني: تسجيل ثم تسليم فوري لوسام اليوم (رصيد الموزّع 5 ← 2 بعد الأول)
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'rows' => [$this->student2->id => [$this->type->id => 1]],
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'instant' => 1, 'stay' => 1,
        'rows' => [$this->student2->id => [$this->type->id => 1]],
    ])->assertSessionHasNoErrors();
    $ent2 = BadgeEntitlement::where('student_id', $this->student2->id)->firstOrFail();
    expect($this->balances->studentBalance($this->student2->id, $this->type->id))->toBe(1)
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(1);

    // إزالة العلامة مع الحفظ الجماعي: حذف فعلي — التوزيع يُلغى والوسام يعود
    // لرصيد الموزّع والاستحقاق يُلغى، لا حدّ أرضي يبقيه
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'rows' => [$this->student2->id => [$this->type->id => 0]],
    ])->assertSessionHasNoErrors();
    expect($ent2->fresh()->status->value)->toBe('cancelled')
        ->and($this->balances->studentBalance($this->student2->id, $this->type->id))->toBe(0)
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(2)
        ->and(BadgeDistribution::active()->where('entitlement_id', $ent2->id)->count())->toBe(0);
});

test('daily sheet two checkboxes: deliver flag delivers per badge, unchecking it undoes today delivery only', function () {
    $ctx = ['date' => '2026-11-22', 'circle_id' => $this->circleA->id, 'teacher_id' => $this->teacherUser->id];
    $this->stock->recordMovement($this->type, 100, $this->admin);
    $this->handoverService->handover($this->teacherUser, $this->type, 10, '2026-11-21', $this->admin);

    // استحقاق + تسليم فوري في حفظ واحد (بلا العلم العام)
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'rows' => [$this->student1->id => [$this->type->id => 1]],
        'deliver' => [$this->student1->id => [$this->type->id => 1]],
    ])->assertSessionHasNoErrors();
    $ent = BadgeEntitlement::where('student_id', $this->student1->id)->firstOrFail();
    expect($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(1)
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(9);

    // إزالة علامة التسليم فقط: تسليم اليوم يُلغى والوسام يرجع للموزّع، والاستحقاق يبقى
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'rows' => [$this->student1->id => [$this->type->id => 1]],
        'deliver' => [$this->student1->id => [$this->type->id => 0]],
    ])->assertSessionHasNoErrors();
    expect($ent->fresh()->status->value)->not->toBe('cancelled')
        ->and($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(0)
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(10);

    // «تسليم فوري» وحدها على متبقٍّ قديم: تُسلّمه دون اختراع استحقاق لليوم
    $this->entitlementService->create([
        'student_id' => $this->student2->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 2, 'entitlement_date' => '2026-11-20',
    ], $this->admin);
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.daily-store'), $ctx + [
        'rows' => [$this->student2->id => [$this->type->id => 0]],
        'deliver' => [$this->student2->id => [$this->type->id => 1]],
    ])->assertSessionHasNoErrors();
    expect($this->balances->studentBalance($this->student2->id, $this->type->id))->toBe(2)
        ->and(BadgeEntitlement::where('student_id', $this->student2->id)->whereDate('entitlement_date', '2026-11-22')->count())->toBe(0);
});

test('entitlement statistics: distributor and student detail matrices behind show-more links', function () {
    $type2 = BadgeType::create(['name' => 'وسام التميز', 'is_active' => true]);
    $this->stock->recordMovement($this->type, 100, $this->admin);
    $this->stock->recordMovement($type2, 100, $this->admin);
    $this->handoverService->handover($this->teacherUser, $this->type, 20, '2026-11-01', $this->admin);
    $this->handoverService->handover($this->teacherUser, $type2, 20, '2026-11-01', $this->admin);

    $mk = fn ($student, $type, $qty) => $this->entitlementService->create([
        'student_id' => $student->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $type->id,
        'quantity' => $qty, 'entitlement_date' => '2026-11-20',
    ], $this->admin);
    $e1 = $mk($this->student1, $this->type, 3);
    $this->distributionService->distribute($e1, 2, '2026-11-21', $this->admin);
    $e2 = $mk($this->student1, $type2, 1);
    $this->distributionService->distribute($e2, 1, '2026-11-21', $this->admin);
    $e3 = $mk($this->student2, $this->type, 5);
    $this->distributionService->distribute($e3, 5, '2026-11-21', $this->admin);

    // صفحة الإحصائيات: قسم الأوائل وروابط عرض المزيد
    $this->actingAs($this->admin)->get(route('badges.entitlements.statistics'))
        ->assertOk()
        ->assertSee('الطلاب الحاصلون على أوسمة أكثر')
        ->assertSee('عرض المزيد')
        ->assertSee($this->student2->name);

    // تفاصيل الموزّعين: صف واحد للموزّع بأعمدة لكل نوع وإجماليين (مستحق 9، موزّع 8)
    $resp = $this->actingAs($this->admin)->get(route('badges.entitlements.statistics-distributors'))->assertOk();
    $resp->assertSee($this->teacherUser->name)->assertSee('وسام التميز');
    $view = $resp->viewData('matrix');
    expect($view[$this->teacherUser->id]['entitled'][$this->type->id])->toBe(8)
        ->and($view[$this->teacherUser->id]['delivered'][$this->type->id])->toBe(7)
        ->and($view[$this->teacherUser->id]['total_entitled'])->toBe(9)
        ->and($view[$this->teacherUser->id]['total_delivered'])->toBe(8);

    // تفاصيل الطلاب: مرتّبة بالموزّع فعليًا (الثاني 5 قبل الأول 3) مع اسم الحلقة
    $resp = $this->actingAs($this->admin)->get(route('badges.entitlements.statistics-students'))->assertOk();
    $resp->assertSeeInOrder([$this->student2->name, $this->student1->name])
        ->assertSee($this->circleA->name);
    $view = $resp->viewData('matrix');
    expect($view[$this->student1->id]['total_delivered'])->toBe(3)
        ->and($view[$this->student2->id]['total_entitled'])->toBe(5);

    // صفحتا التفاصيل: زر التقرير وحقل البحث والفلاتر تعمل
    $this->actingAs($this->admin)->get(route('badges.entitlements.statistics-distributors'))
        ->assertOk()->assertSee('تنزيل التقرير')->assertSee('بحث باسم المسؤول')->assertSee('عدد المسؤولين');
    $this->actingAs($this->admin)->get(route('badges.entitlements.statistics-students'))
        ->assertOk()->assertSee('تنزيل التقرير')->assertSee('بحث باسم الطالب')->assertSee('عدد الطلاب');
    $resp = $this->actingAs($this->admin)->get(route('badges.entitlements.statistics-distributors', ['q' => 'اسم غير موجود']));
    expect($resp->viewData('matrix'))->toBeEmpty();
    $resp = $this->actingAs($this->admin)->get(route('badges.entitlements.statistics-students', ['q' => $this->student2->name]));
    expect(array_keys($resp->viewData('matrix')))->toBe([$this->student2->id]);
    $resp = $this->actingAs($this->admin)->get(route('badges.entitlements.statistics-students', ['month' => '2026-10']));
    expect($resp->viewData('matrix'))->toBeEmpty();
});

test('entitlements page: self-return card lets a teacher return his own custody to admin', function () {
    badgeSeedStockAndHandover($this); // المعلم معه 40، والمركزي 60

    // البطاقة في صفحة الاستحقاقات (صفحة عمله) لا التسليمات (لا يراها أصلًا)
    $this->actingAs($this->teacherUser)->get(route('badges.handovers.index'))->assertRedirect();
    $page = $this->actingAs($this->teacherUser)->get(route('badges.entitlements.index'));
    $page->assertOk()->assertSee('إرجاع أوسمة للإدارة (من عهدتي)');
    expect($page->viewData('returnBalances')[$this->type->id])->toBe(40);

    // إرجاع 5 من عهدته من نفس الصفحة: يعود إليها، وتنتقل الكمية للمركزي
    $this->actingAs($this->teacherUser)
        ->from(route('badges.entitlements.index'))
        ->post(route('badges.stock.returns.store'), [
            'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id, 'quantity' => 5,
        ])->assertRedirect(route('badges.entitlements.index'));
    expect($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(35)
        ->and($this->balances->adminBalance($this->type->id))->toBe(65);

    // ولا يستطيع الإرجاع من عهدة غيره
    $this->actingAs($this->teacherUser)
        ->from(route('badges.entitlements.index'))
        ->post(route('badges.stock.returns.store'), [
            'teacher_id' => $this->admin->id, 'badge_type_id' => $this->type->id, 'quantity' => 1,
        ])->assertSessionHasErrors('teacher_id');
    expect($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(35);
});

test('badge access redesign: types and stock are admin-only, reports and statistics are admin-only, teacher sees no handovers', function () {
    $gmUser = User::factory()->create(['status' => 'active']);
    $gmUser->assignRole('general_manager');
    $managerUser = User::factory()->create(['status' => 'active']);
    $managerUser->assignRole('manager');
    $supervisorUser = User::factory()->create(['status' => 'active']);
    $supervisorUser->assignRole('supervisor');

    // الأنواع والمخزون المركزي: مدير النظام فقط
    $this->actingAs($gmUser)->get(route('badges.types.index'))->assertRedirect();
    $this->actingAs($supervisorUser)->get(route('badges.stock.index'))->assertRedirect();
    $this->actingAs($managerUser)->get(route('badges.stock.history'))->assertRedirect();
    $this->actingAs($this->admin)->get(route('badges.types.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('badges.stock.index'))->assertOk();

    // التقارير والإحصائيات: مدير النظام فقط (بوابة دور لا صلاحية)
    $this->actingAs($gmUser)->get(route('badges.entitlements.statistics'))->assertRedirect();
    $this->actingAs($supervisorUser)->get(route('badges.handovers.statistics'))->assertRedirect();
    $this->actingAs($this->admin)->get(route('badges.entitlements.statistics'))->assertOk();
    $this->actingAs($this->admin)->get(route('badges.handovers.statistics'))->assertOk();

    // لا صلاحيات توزيعات مستقلة ولا تقارير لأي دور بعد الدمج والحذف
    foreach (['general_manager', 'manager', 'supervisor', 'teacher'] as $roleName) {
        $names = \Spatie\Permission\Models\Role::findByName($roleName)->permissions->pluck('name');
        expect($names)->not->toContain('badges.distributions.view')
            ->not->toContain('badges.distributions.create')
            ->not->toContain('badges.distributions.cancel')
            ->not->toContain('badges.reports.view')
            ->not->toContain('badges.types.view')
            ->not->toContain('badges.stock.view')
            ->not->toContain('badges.stock.return')
            ->not->toContain('badges.handovers.view')
            ->not->toContain('badges.collections.view');
    }
    expect(\Spatie\Permission\Models\Role::findByName('teacher')->permissions->pluck('name'))
        ->not->toContain('badges.handovers.view');
});


test('badge permission seeder deletes orphan badge permission rows', function () {
    // صفوف يتيمة من عائلات محذوفة (أنواع/سجل/استبدالات) ومنح قديم لأحدها
    foreach (['badges.types.view', 'badges.logs.view', 'badges.redemptions.view'] as $stale) {
        \Spatie\Permission\Models\Permission::create(['name' => $stale, 'guard_name' => 'web']);
    }
    $supervisorRole = \Spatie\Permission\Models\Role::findByName('supervisor');
    $supervisorRole->givePermissionTo('badges.types.view');
    expect(\Spatie\Permission\Models\Permission::where('name', 'like', 'badges.%')->count())->toBe(9);

    $this->seed(\Database\Seeders\BadgePermissionSeeder::class);

    // لم يبقَ إلا الست المعرّفة حاليًا، والمنح اليتيم زال مع صفه
    $remaining = \Spatie\Permission\Models\Permission::where('name', 'like', 'badges.%')->pluck('name')->sort()->values()->all();
    expect($remaining)->toBe([
        'badges.entitlements.cancel', 'badges.entitlements.create', 'badges.entitlements.update', 'badges.entitlements.view',
        'badges.shortages.resolve', 'badges.shortages.view',
    ])->and($supervisorRole->fresh()->hasPermissionTo('badges.entitlements.view'))->toBeTrue();
});

test('handover show page is removed', function () {
    badgeSeedStockAndHandover($this);
    $handover = \App\Models\Badges\BadgeTeacherHandover::first();
    // لا صفحة عرض لتسليم مفرد — القائمة تعرض العمليات مجمعة أصلًا
    $this->actingAs($this->admin)->get('/badges/handovers/' . $handover->id)->assertNotFound();
    $this->actingAs($this->admin)->get(route('badges.handovers.index'))->assertOk();
});

test('entitlement show page is removed, edit and cancel live on the index', function () {
    badgeSeedStockAndHandover($this);
    $entitlement = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 4, 'entitlement_date' => '2026-11-10',
    ], $this->admin);

    // لا صفحة عرض: نفس العنوان له مسارات تعديل فقط (405 لطلب GET)
    expect($this->actingAs($this->admin)->get('/badges/entitlements/' . $entitlement->id)->status())->toBeIn([404, 405]);
    $page = $this->actingAs($this->admin)->get(route('badges.entitlements.index'));
    $page->assertOk()->assertSee(route('badges.entitlements.edit', $entitlement));
});

test('فلتر الشهر في صفحة جولات تحصيل الأوسمة', function () {
    $mk = fn (string $date) => \App\Models\Badges\BadgeCollectionRound::create([
        'circle_id' => $this->circleA->id,
        'teacher_id' => $this->admin->id,
        'collection_date' => $date,
        'status' => 'completed',
        'created_by' => $this->admin->id,
    ]);
    $nov = $mk('2026-11-15');
    $oct = $mk('2026-10-15');

    $resp = $this->actingAs($this->admin)->get(route('badges.collections.index', ['month' => '2026-11']));
    $resp->assertOk();
    $ids = collect($resp->viewData('rounds')->items())->pluck('id');
    expect($ids)->toContain($nov->id)->not->toContain($oct->id);

    $all = $this->actingAs($this->admin)->get(route('badges.collections.index'));
    $allIds = collect($all->viewData('rounds')->items())->pluck('id');
    expect($allIds)->toContain($nov->id)->toContain($oct->id);
});

test('collections: same circle cannot be collected twice in one month', function () {
    badgeSeedStockAndHandover($this);

    $supervisor = User::factory()->create(['status' => 'active']);
    $supervisor->assignRole('supervisor');
    Teacher::create(['user_id' => $supervisor->id, 'branch_id' => $this->branchA->id]);

    $e = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 50, 'entitlement_date' => '2026-11-10',
    ], $this->admin);
    $this->distributionService->distribute($e, 7, '2026-11-11', $this->teacherUser);

    // جولة معتمدة سابقة لنفس الحلقة في نوفمبر
    \App\Models\Badges\BadgeCollectionRound::create([
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-11-05', 'status' => 'completed', 'created_by' => $this->admin->id,
    ]);

    // بدء جلسة جديدة في نفس الشهر مرفوض ولا ينشئ مسودة
    $this->actingAs($this->admin)->post(route('badges.collections.settle-student'), [
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-11-20', 'student_id' => $this->student1->id,
        'amounts' => [$this->type->id => ['good' => 1, 'damaged' => 0, 'owed' => 0]],
    ])->assertSessionHasErrors('collection_date');
    expect(\App\Models\Badges\BadgeCollectionRound::where('status', 'draft')->count())->toBe(0);

    // صفحة التحصيل تعرض المنع عند اختيار الشهر المحصَّل
    $page = $this->actingAs($this->admin)->get(route('badges.collections.create', [
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id, 'month' => '2026-11',
    ]));
    $page->assertOk()->assertSee('لا يمكن تحصيل الحلقة أكثر من مرة في الشهر الواحد');
    expect($page->viewData('monthCollected'))->not->toBeNull()
        ->and($page->viewData('month'))->toBe('2026-11');

    // شهر آخر متاح: التسوية تنجح وتفتح مسودة
    $this->actingAs($this->admin)->post(route('badges.collections.settle-student'), [
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-12-02', 'student_id' => $this->student1->id,
        'amounts' => [$this->type->id => ['good' => 1, 'damaged' => 0, 'owed' => 0]],
    ])->assertRedirect();
    $draft = \App\Models\Badges\BadgeCollectionRound::where('status', 'draft')->first();
    expect($draft)->not->toBeNull();

    // سباق الاعتماد: جولة أخرى اكتملت في ديسمبر أولًا → اعتماد المسودة مرفوض وتبقى مسودة
    \App\Models\Badges\BadgeCollectionRound::create([
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-12-10', 'status' => 'completed', 'created_by' => $this->admin->id,
    ]);
    $this->actingAs($this->admin)->post(route('badges.collections.complete', $draft))->assertSessionHasErrors();
    expect($draft->fresh()->status->value)->toBe('draft');
});

test('collections create: student select asks for the circle first', function () {
    badgeSeedStockAndHandover($this);
    $e = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 5, 'entitlement_date' => '2026-11-10',
    ], $this->admin);
    $this->distributionService->distribute($e, 5, '2026-11-11', $this->teacherUser);

    // بلا اختيار حلقة: حقل الطالب يعرض «اختر الحلقة أولاً» ولا حلقة افتراضية
    $page = $this->actingAs($this->admin)->get(route('badges.collections.create'));
    $page->assertOk()->assertSee('اختر الحلقة أولاً');
    expect($page->viewData('circle'))->toBeNull();

    // بعد اختيار الحلقة: طلابها يظهرون وتختفي الرسالة
    $withCircle = $this->actingAs($this->admin)->get(route('badges.collections.create', ['circle_id' => $this->circleA->id]));
    $withCircle->assertOk()->assertDontSee('اختر الحلقة أولاً')->assertSee($this->student1->name);
    expect($withCircle->viewData('circle')?->id)->toBe($this->circleA->id);
});

test('collections create: context filters update in place without a page reload', function () {
    $page = $this->actingAs($this->admin)->get(route('badges.collections.create'));
    $page->assertOk()
        ->assertSee('id="collections-workspace"', false)
        ->assertSee('id="collections-context-form"', false)
        ->assertSee('bindSwalConfirms', false)
        ->assertDontSee('this.form.submit', false);
});

test('student show: badges and competitions tabs show the student data', function () {
    badgeSeedStockAndHandover($this);

    $supervisor = User::factory()->create(['status' => 'active']);
    $supervisor->assignRole('supervisor');
    Teacher::create(['user_id' => $supervisor->id, 'branch_id' => $this->branchA->id]);

    // استحقاقان (10 + 5) سُلّم منهما 6 + 5: المستحق 15 والمسلّم 11 وبقي مستحق 4
    $e = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 10, 'entitlement_date' => '2026-11-10',
    ], $this->admin);
    $this->distributionService->distribute($e, 6, '2026-11-11', $this->teacherUser);
    $e2 = $this->entitlementService->create([
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 5, 'entitlement_date' => '2026-11-10',
    ], $this->admin);
    $this->distributionService->distribute($e2, 5, '2026-11-11', $this->teacherUser);

    // جلسة تحصيل معتمدة: 7 سليم + 2 تالف/مفقود + خصم 1 من المستحق → في اليد 2
    // (تقسيم السليم/التالف باصطلاح النظام نفسه: التوزيع الأقدم يُملأ سليمًا أولًا)
    $round = \App\Models\Badges\BadgeCollectionRound::create([
        'circle_id' => $this->circleA->id, 'teacher_id' => $supervisor->id,
        'collection_date' => '2026-11-20', 'status' => 'draft', 'created_by' => $this->admin->id,
    ]);
    $this->collectionService->settleStudent($round, $this->student1->id, [
        $this->type->id => ['good' => 7, 'damaged' => 2, 'owed' => 1],
    ], $this->admin);
    $this->collectionService->complete($round, $this->admin);

    $competition = \App\Models\Competition::create(['name' => 'مسابقة الحفظ الكبرى', 'status' => 'active']);
    $level = \App\Models\Level::create(['name' => 'المستوى الأول']);
    $competitionLevel = \App\Models\CompetitionLevel::create(['competition_id' => $competition->id, 'level_id' => $level->id]);
    $participant = \App\Models\CompetitionParticipant::create([
        'competition_id' => $competition->id, 'competition_level_id' => $competitionLevel->id, 'student_id' => $this->student1->id,
        'center_id' => $this->centerA->id, 'circle_id' => $this->circleA->id,
        'file_status' => 2, 'registration_fee' => 50,
    ]);
    \App\Models\CompetitionResult::create([
        'competition_participant_id' => $participant->id, 'total_score' => 95, 'rank' => 1,
    ]);

    $page = $this->actingAs($this->admin)->get(route('students.show', $this->student1));
    $page->assertOk()
        ->assertSee('الأوسمة')->assertSee('المسابقات')
        ->assertSee('في يد الطالب حاليًا')
        ->assertSee('مسابقة الحفظ الكبرى')->assertSee('تم الاستلام');

    $summary = $page->viewData('badgeSummary');
    expect($summary)->toHaveCount(1)
        ->and($summary[0]['entitled'])->toBe(15)
        ->and($summary[0]['delivered'])->toBe(11)
        ->and($summary[0]['in_hand'])->toBe(2);

    $collections = $page->viewData('badgeCollections');
    expect($collections)->toHaveCount(1)
        ->and($collections[0]['good'])->toBe(6)
        ->and($collections[0]['damaged'])->toBe(3);

    expect($page->viewData('badgeDistributions'))->toHaveCount(2)
        ->and($page->viewData('badgeDeductions')->sum(fn ($r) => $r->items->sum('shortage_quantity')))->toBe(1);
});
