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

use App\Enums\Badges\StockMovementType;
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
    $test->stock->recordMovement($test->type, StockMovementType::InitialStock, 100, $test->admin);
    $test->handoverService->handover($test->teacherUser, $test->type, 40, '2026-11-01', $test->admin);
}

test('stock: initial stock, additions and adjustments drive the admin balance', function () {
    $this->stock->recordMovement($this->type, StockMovementType::InitialStock, 100, $this->admin);
    expect($this->balances->adminBalance($this->type->id))->toBe(100);

    $this->stock->recordMovement($this->type, StockMovementType::Addition, 50, $this->admin);
    expect($this->balances->adminBalance($this->type->id))->toBe(150);

    $this->stock->recordMovement($this->type, StockMovementType::Adjustment, -20, $this->admin, 'تلف 20 وسامًا');
    expect($this->balances->adminBalance($this->type->id))->toBe(130);

    // تسوية بلا سبب موثق مرفوضة
    try {
        $this->stock->recordMovement($this->type, StockMovementType::Adjustment, -5, $this->admin);
        $this->fail('adjustment without notes must fail');
    } catch (ValidationException) {
        expect(true)->toBeTrue();
    }

    // تسوية تجعل الرصيد سالبًا مرفوضة
    try {
        $this->stock->recordMovement($this->type, StockMovementType::Adjustment, -999, $this->admin, 'سبب');
        $this->fail('negative balance adjustment must fail');
    } catch (ValidationException) {
        expect($this->balances->adminBalance($this->type->id))->toBe(130);
    }
});

test('handover: moves balance from admin to teacher and rejects over-stock', function () {
    $this->stock->recordMovement($this->type, StockMovementType::InitialStock, 30, $this->admin);

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
    $this->stock->recordMovement($this->type, StockMovementType::InitialStock, 100, $this->admin);
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
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 3, 'entitlement_date' => '2026-11-10',
    ])->assertSessionHasErrors('circle_id');
    expect(BadgeEntitlement::count())->toBe(0);

    // وطالب من حلقة أخرى مرفوض حتى للمعلم نفسه
    $studentB = Student::create(['name' => 'طالب ب', 'center_id' => $this->centerB->id, 'circle_id' => $this->circleB->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر', 'supervisor_id' => $this->teacher->id]);
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.store'), [
        'student_id' => $studentB->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 3, 'entitlement_date' => '2026-11-10',
    ])->assertSessionHasErrors('student_id');
    expect(BadgeEntitlement::count())->toBe(0);

    // إنشاء سليم عبر HTTP يعمل ويفتح العجز (لا رصيد)
    $this->actingAs($this->teacherUser)->post(route('badges.entitlements.store'), [
        'student_id' => $this->student1->id, 'circle_id' => $this->circleA->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 3, 'entitlement_date' => '2026-11-10',
    ])->assertSessionHasNoErrors();
    expect(BadgeEntitlement::count())->toBe(1)
        ->and(BadgeShortage::count())->toBe(1);
});

test('badge pages render for authorized users', function () {
    badgeSeedStockAndHandover($this);

    $this->actingAs($this->admin)->get(route('badges.dashboard'))->assertOk();
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
    $this->actingAs($this->admin)->get(route('badges.reports.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('badges.logs.index'))->assertOk();

    $this->actingAs($this->teacherUser)->get(route('badges.dashboard'))->assertOk();
    $this->actingAs($this->teacherUser)->get(route('badges.entitlements.index'))->assertOk();
    $this->actingAs($this->teacherUser)->get(route('badges.shortages.index'))->assertOk();
});
