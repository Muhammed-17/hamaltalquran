<?php
use App\Models\{Branch, Center, Circle, Student, Subscription, Teacher, User};
use Illuminate\Support\Facades\DB;
use Database\Seeders\PermissionSeeder;

/**
 * فحص إنشاء الاشتراك (تقرير 216): خللان في طلبي الإنشاء والتعديل —
 * المحصِّل كان يُقبل أي مستخدم موجود (أُسند قبض مبلغ لولي أمر فعليًا)،
 * و«مدفوع» كان يقبل مبلغ صفر. النطاق وعضوية الحلقة ودمج نفس الشهر
 * سليمة بالتصميم ومثبتة هنا.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $mk = function (string $role, string $tag) {
        $u = User::create(['name' => $tag, 'email' => $tag . '@t.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->assignRole($role);
        return $u;
    };
    $this->mk = $mk;
    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    $this->circle = Circle::create(['name' => 'حلقة', 'branch_id' => $branch->id, 'type' => 'group', 'level' => 'build', 'status' => 'active']);
    $this->teacher = $mk('teacher', 'المعلم');
    $t = Teacher::create(['user_id' => $this->teacher->id, 'branch_id' => $branch->id, 'name' => 'المعلم']);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circle->id, 'teacher_id' => $t->id, 'role' => 'main']);
    $this->guardian = $mk('guardian', 'ولي الأمر');
    $this->student = Student::create(['name' => 'طالب', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول', 'center_id' => $center->id, 'circle_id' => $this->circle->id, 'guardian_id' => $this->guardian->id]);
    $this->payload = [
        'student_id' => $this->student->id, 'circle_id' => $this->circle->id,
        'teacher_id' => $this->teacher->id, 'month' => '2026-11',
        'status' => 'مدفوع', 'amount' => 150, 'payment_method' => 'نقدي',
    ];
});

test('collector must be collection staff on create and update', function () {
    // إسناد القبض لولي أمر (طلب مصوغ): خطأ تحقق ولا يُنشأ شيء
    $this->actingAs($this->teacher)->post(route('subscriptions.store'), array_merge($this->payload, ['collected_by' => $this->guardian->id]))
        ->assertSessionHasErrors('collected_by');
    expect(Subscription::count())->toBe(0);

    // محصِّل من الطاقم يعمل
    $other = ($this->mk)('teacher', 'معلم محصّل');
    $this->actingAs($this->teacher)->post(route('subscriptions.store'), array_merge($this->payload, ['collected_by' => $other->id]))
        ->assertSessionHasNoErrors();
    $sub = Subscription::first();
    expect($sub->collected_by)->toBe($other->id);

    // ونفس القيد على التعديل
    $this->actingAs($this->teacher)->put(route('subscriptions.update', $sub), array_merge($this->payload, ['collected_by' => $this->guardian->id]))
        ->assertSessionHasErrors('collected_by');
    expect($sub->fresh()->collected_by)->toBe($other->id);
});

test('same student and month merges into one record by design', function () {
    // سلوك مقصود وموثق في المتحكم: إعادة الإنشاء لنفس الشهر تحدّث السجل
    // القائم بدل إنشاء مكرر (حاملو الإنشاء هم حاملو التعديل أنفسهم)
    $this->actingAs($this->teacher)->post(route('subscriptions.store'), $this->payload)->assertSessionHasNoErrors();
    $this->actingAs($this->teacher)->post(route('subscriptions.store'), array_merge($this->payload, ['amount' => 200]))->assertSessionHasNoErrors();
    expect(Subscription::count())->toBe(1)
        ->and((float) Subscription::first()->amount)->toBe(200.0);
});

test('paid subscriptions require a positive amount', function () {
    $this->actingAs($this->teacher)->post(route('subscriptions.store'), array_merge($this->payload, ['amount' => 0]))
        ->assertSessionHasErrors('amount');
    expect(Subscription::count())->toBe(0);

    // غير المدفوع بمبلغ صفر سليم (يُصفَّر أصلًا في المتحكم)
    $this->actingAs($this->teacher)->post(route('subscriptions.store'), array_merge($this->payload, ['status' => 'غير مدفوع', 'amount' => 0, 'payment_method' => null]))
        ->assertSessionHasNoErrors();
    expect(Subscription::count())->toBe(1);
});
