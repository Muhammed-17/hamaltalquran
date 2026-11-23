<?php

/**
 * تدقيق نظام الأوسمة (webapp-testing): البند المختلط سليم+تالف، ثبات الحفظ،
 * وحرّاس العمليات الخطرة. البند الواحد قد يجمع سليمًا وتالفًا منذ التسوية
 * طالبًا طالبًا — الجزء التالف محفوظ في damaged_quantity والسليم يعود للعهدة.
 */

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

function auditUser(string $role, string $email): User
{
    $u = User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('Test123!'), 'status' => 'active']);
    $u->assignRole($role);
    return $u;
}

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\BadgePermissionSeeder::class);

    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    $this->circle = Circle::create(['name' => 'حلقة', 'type' => 'group', 'level' => 'build', 'branch_id' => $branch->id]);

    $this->admin = auditUser('admin', 'admin@audit.local');
    $this->sup = auditUser('supervisor', 'sup@audit.local');
    $supT = Teacher::create(['user_id' => $this->sup->id, 'branch_id' => $branch->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $branch->id, 'teacher_id' => $supT->id]);
    $this->teacherUser = auditUser('teacher', 't@audit.local');
    $t = Teacher::create(['user_id' => $this->teacherUser->id, 'branch_id' => $branch->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circle->id, 'teacher_id' => $t->id, 'role' => 'main']);

    $this->student = Student::create(['name' => 'طالب', 'center_id' => $center->id, 'circle_id' => $this->circle->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
    $this->type = BadgeType::create(['name' => 'وسام', 'is_active' => true]);
    $this->bal = app(BadgeBalanceService::class);

    app(BadgeStockService::class)->recordMovement($this->type, 100, $this->admin);
    app(BadgeTeacherHandoverService::class)->handover($this->teacherUser, $this->type, 40, '2026-11-01', $this->admin);
});

test('mixed collection item: the good part returns to custody, the damaged part does not', function () {
    $e = app(BadgeEntitlementService::class)->create([
        'student_id' => $this->student->id, 'circle_id' => $this->circle->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 10, 'entitlement_date' => '2026-11-02',
    ], $this->admin);
    app(BadgeDistributionService::class)->distribute($e, 10, '2026-11-03', $this->teacherUser);

    $supBefore = $this->bal->teacherBalance($this->sup->id, $this->type->id);
    $coll = app(BadgeCollectionService::class);
    $round = $coll->findOrCreateSessionRound($this->circle, $this->sup->id, '2026-11-10', $this->sup);
    // توزيع واحد من 10: يُحصَّل 6 سليم + 2 تالف/مفقود — بند واحد مختلط
    $coll->settleStudent($round, $this->student->id, [$this->type->id => ['good' => 6, 'damaged' => 2, 'owed' => 0]], $this->sup);

    $settled = $coll->settledStudents($round);
    $row = collect($settled[$this->student->id]['types'])->firstWhere('type.id', $this->type->id);
    expect($row['good'])->toBe(6)
        ->and($row['damaged'])->toBe(2);

    $coll->complete($round, $this->sup);

    // العهدة تزيد بالسليم فقط، ويد الطالب تنقص بالمجموع، وإجمالي التالف = 2
    expect($this->bal->teacherBalance($this->sup->id, $this->type->id) - $supBefore)->toBe(6)
        ->and($this->bal->studentBalance($this->student->id, $this->type->id))->toBe(2)
        ->and($this->bal->totalDamaged($this->type->id))->toBe(2);
});

test('badge conservation holds across the whole lifecycle', function () {
    $stock = app(BadgeStockService::class);
    $ent = app(BadgeEntitlementService::class);
    $dist = app(BadgeDistributionService::class);
    $coll = app(BadgeCollectionService::class);

    $total = fn (int $damagedOut) => $this->bal->adminBalance($this->type->id)
        + $this->bal->teacherBalance($this->sup->id, $this->type->id)
        + $this->bal->teacherBalance($this->teacherUser->id, $this->type->id)
        + $this->bal->studentBalance($this->student->id, $this->type->id)
        + $damagedOut;

    expect($total(0))->toBe(100);

    $e = $ent->create([
        'student_id' => $this->student->id, 'circle_id' => $this->circle->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 12, 'entitlement_date' => '2026-11-02',
    ], $this->admin);
    $d = $dist->distribute($e, 10, '2026-11-03', $this->teacherUser);
    expect($total(0))->toBe(100);

    $stock->returnFromTeacher($this->teacherUser, $this->type, 5, $this->teacherUser);
    expect($total(0))->toBe(100);

    $round = $coll->findOrCreateSessionRound($this->circle, $this->sup->id, '2026-11-10', $this->sup);
    $coll->settleStudent($round, $this->student->id, [$this->type->id => ['good' => 6, 'damaged' => 2, 'owed' => 1]], $this->sup);
    $coll->complete($round, $this->sup);
    expect($total(2))->toBe(100);

    // اعتماد ثانٍ لنفس الجولة مرفوض ولا يضاعف العهدة
    expect(fn () => $coll->complete($round->fresh(), $this->sup))->toThrow(ValidationException::class);
    // إلغاء توزيع دخل تحصيلًا مكتملًا مرفوض
    expect(fn () => $dist->cancel($d, $this->admin))->toThrow(ValidationException::class);
    expect($total(2))->toBe(100);
});

test('dangerous badge operations stay guarded', function () {
    // إرجاع من عهدة معلم آخر: مرفوض ولو كان الرصيد يكفي
    $other = auditUser('teacher', 'other@audit.local');
    Teacher::create(['user_id' => $other->id, 'branch_id' => Branch::first()->id]);
    app(BadgeTeacherHandoverService::class)->handover($other, $this->type, 15, '2026-11-01', $this->admin);

    $this->actingAs($this->teacherUser)->post(route('badges.stock.returns.store'), [
        'teacher_id' => $other->id, 'badge_type_id' => $this->type->id, 'quantity' => 5,
    ])->assertSessionHasErrors('teacher_id');
    expect($this->bal->teacherBalance($other->id, $this->type->id))->toBe(15);

    // إلغاء تسليم وُزّع جزء منه: مرفوض
    $e = app(BadgeEntitlementService::class)->create([
        'student_id' => $this->student->id, 'circle_id' => $this->circle->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 10, 'entitlement_date' => '2026-11-02',
    ], $this->admin);
    app(BadgeDistributionService::class)->distribute($e, 10, '2026-11-03', $this->teacherUser);
    $handover = \App\Models\Badges\BadgeTeacherHandover::where('teacher_id', $this->teacherUser->id)->first();
    expect(fn () => app(BadgeTeacherHandoverService::class)->cancel($handover, $this->admin))
        ->toThrow(ValidationException::class);

    // تقليص استحقاق دون الموزّع فعليًا عبر التعديل: مرفوض وتبقى الكمية
    $this->actingAs($this->admin)->put(route('badges.entitlements.update', $e), [
        'student_id' => $this->student->id, 'circle_id' => $this->circle->id,
        'teacher_id' => $this->teacherUser->id, 'badge_type_id' => $this->type->id,
        'quantity' => 3, 'entitlement_date' => '2026-11-02',
    ])->assertSessionHasErrors();
    expect($e->fresh()->quantity)->toBe(10);
});
