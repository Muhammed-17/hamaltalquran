<?php
use App\Models\{Branch, Center, Circle, Student, Subscription, SubscriptionTransfer, Teacher, User};
use Illuminate\Support\Facades\DB;
use Database\Seeders\PermissionSeeder;

/**
 * فحص صفحة تحويل الأموال (تقرير 214): خللان كانا مؤكدين بالاختبار —
 * سجل التحويلات كان يعرض كل المراكز لأي حامل للصلاحية (بلا قيد نطاق)،
 * والتحويل كان يقبل أي مستخدم وجهةً (ولي أمر مثلًا) لأن التحقق اكتفى
 * بالوجود. نطاق الحلقة في البحث والتحويل نفسه كان سليمًا ومثبت هنا.
 */

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $mk = function (string $role, string $tag) {
        $u = User::create(['name' => $tag, 'email' => $tag . '@t.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->assignRole($role);
        return $u;
    };
    $this->mk = $mk;
    $centerA = Center::create(['name' => 'مركز أ']);
    $centerB = Center::create(['name' => 'مركز ب']);
    $branchA = Branch::create(['name' => 'فرع أ', 'center_id' => $centerA->id]);
    $branchB = Branch::create(['name' => 'فرع ب', 'center_id' => $centerB->id]);
    $this->circleA = Circle::create(['name' => 'حلقة أ', 'branch_id' => $branchA->id, 'type' => 'group', 'level' => 'build', 'status' => 'active']);
    $this->circleB = Circle::create(['name' => 'حلقة ب', 'branch_id' => $branchB->id, 'type' => 'group', 'level' => 'build', 'status' => 'active']);
    // مشرف على فرع أ فقط
    $this->supervisor = $mk('supervisor', 'مشرف أ');
    $supT = Teacher::create(['user_id' => $this->supervisor->id, 'branch_id' => $branchA->id, 'name' => 'مشرف أ']);
    DB::table('branch_teacher')->insert(['branch_id' => $branchA->id, 'teacher_id' => $supT->id]);
    $this->teacherA = $mk('teacher', 'معلم أ');
    $this->teacherB = $mk('teacher', 'معلم ب');
    $this->guardian = $mk('guardian', 'ولي أمر');
    $studentB = Student::create(['name' => 'طالب ب', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول', 'center_id' => $centerB->id, 'circle_id' => $this->circleB->id, 'guardian_id' => $this->guardian->id]);
    $this->subB = Subscription::create(['student_id' => $studentB->id, 'circle_id' => $this->circleB->id, 'month' => '2026-11-01', 'amount' => 100, 'status' => 'مدفوع', 'collected_by' => $this->teacherB->id, 'paid_at' => now()]);
    SubscriptionTransfer::create(['circle_id' => $this->circleB->id, 'month' => '2026-11-01', 'from_user_id' => $this->teacherB->id, 'to_user_id' => $this->teacherA->id, 'performed_by' => $this->supervisor->id, 'subscription_ids' => [$this->subB->id], 'subscriptions_count' => 1]);
    $studentA = Student::create(['name' => 'طالب أ', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول', 'center_id' => $centerA->id, 'circle_id' => $this->circleA->id, 'guardian_id' => $this->guardian->id]);
    $this->subA = Subscription::create(['student_id' => $studentA->id, 'circle_id' => $this->circleA->id, 'month' => '2026-11-01', 'amount' => 100, 'status' => 'مدفوع', 'collected_by' => $this->teacherA->id, 'paid_at' => now()]);
});

test('transfer history is scoped to the viewer circles', function () {
    // المشرف على فرع أ لا يرى تحويلات مركز ب (كانت ظاهرة لكل حامل للصلاحية)
    $res = $this->actingAs($this->supervisor)->get(route('subscription-transfers.history'));
    $res->assertOk()->assertDontSee('حلقة ب');

    // الأدمن يرى الكل
    $admin = ($this->mk)('admin', 'الأدمن');
    $this->actingAs($admin)->get(route('subscription-transfers.history'))
        ->assertOk()->assertSee('حلقة ب');
});

test('transfer targets must be collection staff, not any user', function () {
    // التحويل إلى ولي أمر (طلب مصوغ): يُرفض ويبقى المحصِّل كما هو
    $this->actingAs($this->supervisor)->postJson(route('subscription-transfers.transfer'), [
        'circle_id' => $this->circleA->id, 'month' => '2026-11',
        'from_user_id' => $this->teacherA->id, 'to_user_id' => $this->guardian->id,
        'subscription_ids' => [$this->subA->id],
    ])->assertUnprocessable();
    expect($this->subA->fresh()->collected_by)->toBe($this->teacherA->id);

    // والتحويل السليم لمعلم آخر يعمل ويُسجَّل
    $other = ($this->mk)('teacher', 'معلم آخر');
    $this->actingAs($this->supervisor)->postJson(route('subscription-transfers.transfer'), [
        'circle_id' => $this->circleA->id, 'month' => '2026-11',
        'from_user_id' => $this->teacherA->id, 'to_user_id' => $other->id,
        'subscription_ids' => [$this->subA->id],
    ])->assertOk();
    expect($this->subA->fresh()->collected_by)->toBe($other->id)
        ->and(SubscriptionTransfer::where('circle_id', $this->circleA->id)->count())->toBe(1);
});

test('search and transfer stay inside the actor circle scope', function () {
    $this->actingAs($this->supervisor)->getJson(route('subscription-transfers.search', ['circle_id' => $this->circleB->id, 'month' => '2026-11', 'collected_by' => $this->teacherB->id]))
        ->assertForbidden();
    $this->actingAs($this->supervisor)->postJson(route('subscription-transfers.transfer'), [
        'circle_id' => $this->circleB->id, 'month' => '2026-11',
        'from_user_id' => $this->teacherB->id, 'to_user_id' => $this->teacherA->id,
        'subscription_ids' => [$this->subB->id],
    ])->assertForbidden();
    expect($this->subB->fresh()->collected_by)->toBe($this->teacherB->id);
});
