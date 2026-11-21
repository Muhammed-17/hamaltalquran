<?php

/*
|--------------------------------------------------------------------------
| سلامة الحضور والاشتراكات وتحصيل الاشتراكات (فحص 2026-11-21)
|--------------------------------------------------------------------------
| - إنشاء/تعديل الاشتراك لم يتحقق أن الطالب عضو في الحلقة المختارة:
|   أمكن تسجيل دفع لطالب أجنبي تحت حلقة المستخدم، وإعادة كتابة سجل
|   الطالب الأجنبي الموجود لنفس الشهر، وإعادة تعيين اشتراك لطالب آخر،
|   ونقل اشتراك لشهر مكرر لنفس الطالب (سجلان لنفس الشهر).
| - سياسة جولات التحصيل كانت تمنح المدير حق التعديل/الحذف/التأكيد على
|   جولات أي مركز دون فحص حلقة — صارت مقيدة بنطاق حلقاته.
| - تحويل الاشتراكات (نقل المحصِّل) لم يفحص نطاق الحلقة إطلاقًا.
| - تجميع إحصائيات الحلقات في تقرير الحضور كان بلا قيد نطاق.
*/

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\CollectionRound;
use App\Models\CollectionRoundItem;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->centerA = Center::create(['name' => 'مركز أ']);
    $this->centerB = Center::create(['name' => 'مركز ب']);
    $this->branchA = Branch::create(['center_id' => $this->centerA->id, 'name' => 'فرع أ']);
    $this->branchB = Branch::create(['center_id' => $this->centerB->id, 'name' => 'فرع ب']);
    $this->circleA = Circle::create(['name' => 'حلقة أ', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchA->id]);
    $this->circleB = Circle::create(['name' => 'حلقة ب', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branchB->id]);

    $mk = function (string $role, ?Branch $branch) {
        $u = User::factory()->create(['status' => 'active']);
        $u->assignRole($role);
        $t = Teacher::create(['user_id' => $u->id, 'branch_id' => $branch?->id]);
        return [$u, $t];
    };

    [$this->managerA, $this->managerTeacherA] = $mk('manager', $this->branchA);
    [$this->supervisorA, $this->supervisorTeacherA] = $mk('supervisor', $this->branchA);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branchA->id, 'teacher_id' => $this->supervisorTeacherA->id, 'created_at' => now(), 'updated_at' => now()]);
    [$this->teacherBUser, $this->teacherB] = $mk('teacher', $this->branchB);

    $this->studentA = Student::create(['name' => 'طالب أ', 'center_id' => $this->centerA->id, 'circle_id' => $this->circleA->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر', 'supervisor_id' => $this->supervisorTeacherA->id]);
    $this->studentB = Student::create(['name' => 'طالب ب', 'center_id' => $this->centerB->id, 'circle_id' => $this->circleB->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر', 'supervisor_id' => $this->teacherB->id]);

    $this->subB = Subscription::create(['student_id' => $this->studentB->id, 'circle_id' => $this->circleB->id, 'teacher_id' => $this->teacherBUser->id, 'month' => '2026-10-01', 'status' => 'مدفوع', 'amount' => 100, 'payment_method' => 'نقدي', 'paid_at' => now(), 'collected_by' => $this->teacherBUser->id]);
    $this->subA1 = Subscription::create(['student_id' => $this->studentA->id, 'circle_id' => $this->circleA->id, 'teacher_id' => $this->managerA->id, 'month' => '2026-09-01', 'status' => 'مدفوع', 'amount' => 100, 'payment_method' => 'نقدي', 'paid_at' => now(), 'collected_by' => $this->managerA->id]);
    $this->subA2 = Subscription::create(['student_id' => $this->studentA->id, 'circle_id' => $this->circleA->id, 'teacher_id' => $this->managerA->id, 'month' => '2026-10-01', 'status' => 'مدفوع', 'amount' => 100, 'payment_method' => 'نقدي', 'paid_at' => now(), 'collected_by' => $this->managerA->id]);

    $this->roundB = CollectionRound::create(['circle_id' => $this->circleB->id, 'center_id' => $this->centerB->id, 'period_month' => '2026-10-01', 'round_number' => 1, 'total_amount' => 100, 'students_count' => 1, 'status' => 'pending', 'level' => 'build', 'created_by' => $this->teacherBUser->id]);
    CollectionRoundItem::create(['collection_round_id' => $this->roundB->id, 'subscription_id' => $this->subB->id, 'amount_at_collection' => 100, 'collected_by_snapshot' => $this->teacherBUser->id]);

    $this->subPayload = fn (array $over = []) => array_merge([
        'student_id' => $this->studentA->id, 'circle_id' => $this->circleA->id,
        'month' => '2026-08', 'status' => 'مدفوع', 'amount' => 120,
        'payment_method' => 'نقدي', 'teacher_id' => $this->managerA->id,
    ], $over);
});

test('subscription store requires the student to belong to the chosen circle', function () {
    // طالب أجنبي تحت حلقة المدير — مرفوض ولا يُنشأ شيء
    $this->actingAs($this->managerA)->post(route('subscriptions.store'), ($this->subPayload)([
        'student_id' => $this->studentB->id,
    ]))->assertSessionHasErrors('student_id');
    expect(Subscription::where('student_id', $this->studentB->id)->where('month', '2026-08-01')->exists())->toBeFalse();

    // ولا يعيد كتابة سجل الطالب الأجنبي الموجود لنفس الشهر
    $this->actingAs($this->managerA)->post(route('subscriptions.store'), ($this->subPayload)([
        'student_id' => $this->studentB->id, 'month' => '2026-10', 'amount' => 999,
    ]))->assertSessionHasErrors('student_id');
    $after = $this->subB->fresh();
    expect($after->circle_id)->toBe($this->circleB->id)->and((float) $after->amount)->toBe(100.0);

    // والإنشاء الشرعي داخل حلقته يعمل
    $this->actingAs($this->managerA)->post(route('subscriptions.store'), ($this->subPayload)());
    expect(Subscription::where('student_id', $this->studentA->id)->where('month', '2026-08-01')->exists())->toBeTrue();
});

test('subscription update blocks reassignment and duplicate months', function () {
    // إعادة تعيين لطالب أجنبي — مرفوضة
    $this->actingAs($this->managerA)->put(route('subscriptions.update', $this->subA2), ($this->subPayload)([
        'student_id' => $this->studentB->id, 'month' => '2026-10',
    ]))->assertSessionHasErrors('student_id');
    expect($this->subA2->fresh()->student_id)->toBe($this->studentA->id);

    // نقل لشهر له سجل آخر لنفس الطالب — مرفوض ولا تكرار
    $this->actingAs($this->managerA)->put(route('subscriptions.update', $this->subA1), ($this->subPayload)([
        'month' => '2026-10',
    ]))->assertSessionHasErrors('month');
    expect(Subscription::where('student_id', $this->studentA->id)->where('month', '2026-10-01')->count())->toBe(1);

    // تعديل عادي على نفس الطالب والحلقة والشهر يعمل
    $this->actingAs($this->managerA)->put(route('subscriptions.update', $this->subA1), ($this->subPayload)([
        'month' => '2026-09', 'amount' => 130,
    ]));
    expect((float) $this->subA1->fresh()->amount)->toBe(130.0);
});

test('manager cannot edit confirm or delete collection rounds of another center', function () {
    expect($this->managerA->can('update', $this->roundB))->toBeFalse()
        ->and($this->managerA->can('delete', $this->roundB))->toBeFalse()
        ->and($this->managerA->can('confirm', $this->roundB))->toBeFalse();

    $this->actingAs($this->managerA)->patch(route('collection-rounds.confirm.update', $this->roundB));
    expect($this->roundB->fresh()->status)->toBe('pending');

    $this->actingAs($this->managerA)->delete(route('collection-rounds.destroy', $this->roundB));
    expect(CollectionRound::find($this->roundB->id))->not->toBeNull();

    // وجولة حلقته الخاصة يستطيع تأكيدها
    $ownRound = CollectionRound::create(['circle_id' => $this->circleA->id, 'center_id' => $this->centerA->id, 'period_month' => '2026-10-01', 'round_number' => 1, 'total_amount' => 100, 'students_count' => 1, 'status' => 'pending', 'level' => 'build', 'created_by' => $this->supervisorA->id]);
    expect($this->managerA->can('confirm', $ownRound))->toBeTrue();
    $this->actingAs($this->managerA)->patch(route('collection-rounds.confirm.update', $ownRound));
    expect($ownRound->fresh()->status)->toBe('confirmed');
});

test('subscription transfer is limited to accessible circles', function () {
    // تحويل داخل حلقة أجنبية — مرفوض ولا يتغير المحصِّل
    $this->actingAs($this->managerA)->post(route('subscription-transfers.transfer'), [
        'circle_id' => $this->circleB->id, 'month' => '2026-10',
        'from_user_id' => $this->teacherBUser->id, 'to_user_id' => $this->managerA->id,
        'subscription_ids' => [$this->subB->id],
    ]);
    expect($this->subB->fresh()->collected_by)->toBe($this->teacherBUser->id);

    // وتحويل داخل حلقته يعمل
    $this->actingAs($this->managerA)->post(route('subscription-transfers.transfer'), [
        'circle_id' => $this->circleA->id, 'month' => '2026-10',
        'from_user_id' => $this->managerA->id, 'to_user_id' => $this->supervisorA->id,
        'subscription_ids' => [$this->subA2->id],
    ]);
    expect($this->subA2->fresh()->collected_by)->toBe($this->supervisorA->id);
});
