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

    // جولة ثانية: بند التوزيع نفسه يظهر بالمتبقي فقط (4) — لا تحصيل مكرر
    $round2 = $this->collectionService->createRound($this->circleA, $this->teacherUser->id, '2026-11-25', $this->teacherUser);
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
    expect($this->admin->can('badges.types.create'))->toBeTrue()
        ->and($this->teacherUser->can('badges.types.create'))->toBeFalse()
        ->and($this->teacherUser->can('badges.entitlements.create'))->toBeTrue()
        ->and($this->teacherUser->can('badges.stock.add'))->toBeFalse()
        ->and($this->teacherUser->can('badges.handovers.create'))->toBeFalse()
        ->and($this->managerB->can('badges.handovers.create'))->toBeTrue();

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
    $this->actingAs($this->admin)->get(route('badges.logs.index'))->assertOk();

    $this->actingAs($this->teacherUser)->get(route('badges.entitlements.statistics'))->assertOk();
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

    // فلتر الشهر يضيّق الأرقام
    $nov = $this->actingAs($this->admin)->get(route('badges.handovers.statistics', ['month' => '2026-10']));
    expect($nov->viewData('totals')['received'])->toBe(0);
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
        // لا فلتر نوع وسام ولا فلتر حالة عجز في الصفحة
        ->assertDontSee('حالة العجز')
        ->assertDontSee('name="badge_type_id"', false)
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

test('shortages: per-student deliver button distributes every type up to the distributor balance', function () {
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

    // الزر ظاهر في صف الطالب داخل القائمة
    $this->actingAs($this->teacherUser)->get(route('badges.shortages.index'))
        ->assertOk()->assertSee('/badges/shortages/distribute-student', false);

    // ضغطة واحدة للطالب الأول: يُسلَّم 10 من النوع الأول، ويبقى عجز النوع الثاني كاملًا
    $this->actingAs($this->teacherUser)->post(route('badges.shortages.distribute-student'), [
        'teacher_id' => $this->teacherUser->id, 'student_id' => $this->student1->id,
    ])->assertRedirect()->assertSessionHas('success');
    expect($this->balances->studentBalance($this->student1->id, $this->type->id))->toBe(10)
        ->and($this->balances->studentBalance($this->student1->id, $type2->id))->toBe(0)
        ->and($this->balances->teacherBalance($this->teacherUser->id, $this->type->id))->toBe(2);
    $s1t2 = BadgeShortage::where('student_id', $this->student1->id)->where('badge_type_id', $type2->id)->firstOrFail();
    expect($s1t2->shortage_quantity)->toBe(4);

    // الطالب الثاني: الرصيد المتبقي 2 فقط — تسليم جزئي ويبقى عجز 5
    $this->actingAs($this->teacherUser)->post(route('badges.shortages.distribute-student'), [
        'teacher_id' => $this->teacherUser->id, 'student_id' => $this->student2->id,
    ])->assertRedirect();
    expect($this->balances->studentBalance($this->student2->id, $this->type->id))->toBe(2);
    $s2 = BadgeShortage::where('student_id', $this->student2->id)->firstOrFail();
    expect($s2->shortage_quantity)->toBe(5);

    // مدير مركز آخر لا يستطيع الاستكمال نيابة عنه: لا توزيع جديد
    $before = BadgeDistribution::count();
    $denied = $this->actingAs($this->managerB)->post(route('badges.shortages.distribute-student'), [
        'teacher_id' => $this->teacherUser->id, 'student_id' => $this->student2->id,
    ]);
    expect($denied->status())->toBeIn([302, 403])
        ->and(BadgeDistribution::count())->toBe($before);
});
