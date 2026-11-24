<?php

/*
|--------------------------------------------------------------------------
| فحص إنشاء جولة تحصيل الاشتراكات المالية (تقرير 221)
|--------------------------------------------------------------------------
| - مقارنة الشهر كانت تربط Carbon كاملًا (بوقته) بعمود DATE فلا يتطابق
|   أبدًا على SQLite — التدفق ميت في بيئة اختبار المشروع نفسها. صار الربط
|   كنص Y-m-d في كل مواضع الشهر بالخدمة.
| - storeRound لم يكن يفرض قاعدة الصفحة نفسها: لا جولة معلّقة ثانية لنفس
|   الحلقة والشهر — صار يرفضها.
| - نقطة available-circles-for-user كانت تكشف حلقات أي مستخدم لأي حامل
|   إنشاء — صارت حلقات الغير للإدارة العليا فقط، ولكل مستخدم نفسه فقط.
*/

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\CollectionRound;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->center = Center::create(['name' => 'مركز']);
    $this->branch = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع']);
    $this->circle = Circle::create(['name' => 'حلقة', 'type' => 'group', 'level' => 'build', 'branch_id' => $this->branch->id]);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');

    $this->supervisor = User::factory()->create(['status' => 'active']);
    $this->supervisor->assignRole('supervisor');
    $supTeacher = Teacher::create(['user_id' => $this->supervisor->id, 'branch_id' => $this->branch->id]);
    DB::table('branch_teacher')->insert(['branch_id' => $this->branch->id, 'teacher_id' => $supTeacher->id, 'created_at' => now(), 'updated_at' => now()]);

    $this->student = Student::create(['name' => 'طالب', 'center_id' => $this->center->id, 'circle_id' => $this->circle->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر', 'supervisor_id' => $supTeacher->id]);

    $this->paidSub = fn (?Student $student = null) => Subscription::create([
        'student_id' => ($student ?? $this->student)->id, 'circle_id' => $this->circle->id,
        'teacher_id' => $this->supervisor->id, 'month' => '2026-10-01', 'status' => 'مدفوع',
        'amount' => 100, 'payment_method' => 'نقدي', 'paid_at' => now(), 'collected_by' => $this->supervisor->id,
    ]);
});

test('إنشاء جولة تحصيل يعمل من أوله لآخره باشتراك مدفوع مُنشأ بالطريقة الطبيعية', function () {
    $sub = ($this->paidSub)();

    $this->actingAs($this->admin)->post(route('collection-rounds.store'), [
        'circle_id' => $this->circle->id,
        'period_month' => '2026-10',
        'selected_subscription_ids' => [$sub->id],
        'total_amount' => 100,
    ])->assertRedirect(route('collection-rounds.index'));

    $round = CollectionRound::first();
    expect($round)->not->toBeNull()
        ->and($round->status)->toBe('pending')
        ->and($round->items)->toHaveCount(1)
        ->and($sub->fresh()->is_collected)->toBeTrue();
});

test('لا جولة معلّقة ثانية لنفس الحلقة والشهر عبر الحفظ المباشر', function () {
    $sub1 = ($this->paidSub)();
    $student2 = Student::create(['name' => 'طالب ٢', 'center_id' => $this->center->id, 'circle_id' => $this->circle->id, 'status' => 'مقيد', 'decision' => 'مقبول', 'gender' => 'ذكر']);
    $sub2 = ($this->paidSub)($student2);

    $payload = fn ($id) => [
        'circle_id' => $this->circle->id, 'period_month' => '2026-10',
        'selected_subscription_ids' => [$id], 'total_amount' => 100,
    ];

    $this->actingAs($this->admin)->post(route('collection-rounds.store'), $payload($sub1->id))
        ->assertRedirect(route('collection-rounds.index'));

    $this->actingAs($this->admin)->post(route('collection-rounds.store'), $payload($sub2->id))
        ->assertSessionHasErrors('selected_subscription_ids');

    expect(CollectionRound::count())->toBe(1)
        ->and($sub2->fresh()->is_collected)->toBeFalse();
});

test('حلقات مستخدم آخر لا تُكشف إلا للإدارة العليا', function () {
    $url = fn ($uid) => route('collection-rounds.available-circles-for-user', ['user_id' => $uid, 'period_month' => '2026-10']);

    // مشرف يطلب حلقات الأدمن: ممنوع
    $this->actingAs($this->supervisor)->getJson($url($this->admin->id))->assertForbidden();
    // مشرف يطلب حلقاته هو: مسموح
    $this->actingAs($this->supervisor)->getJson($url($this->supervisor->id))->assertOk();
    // أدمن يطلب حلقات المشرف (قائمة المنشئين): مسموح
    $this->actingAs($this->admin)->getJson($url($this->supervisor->id))->assertOk();
});
