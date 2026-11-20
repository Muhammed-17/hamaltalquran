<?php

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CircleAssignmentHistory;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\StudentUnpaidMonths;
use App\Models\User;
use App\Notifications\SequentialAbsenceNotification;
use App\Notifications\UnpaidSubscriptionNotification;
use Database\Seeders\PermissionSeeder;

/*
|--------------------------------------------------------------------------
| اختبارات تدفق الإشعارات (قناة قاعدة البيانات داخل التطبيق)
|--------------------------------------------------------------------------
| ملحوظة: لا توجد أي قناة واتساب/Twilio منفذة في الكود — الإشعارات
| داخلية فقط (قناة قاعدة البيانات). حزمة twilio/sdk حُذفت من المشروع
| بقرار من المالك لأن خدمتها غير مجانية وكانت غير مستخدمة أصلًا.
| كل الاختبارات هنا على بيانات وهمية ولا ترسل أي شيء خارجيًا.
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');

    $makeGuardian = function () {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('guardian');

        return $user;
    };
    $this->guardian1 = $makeGuardian();
    $this->guardian2 = $makeGuardian();

    $center = Center::create(['name' => 'مركز الإشعارات']);
    $branch = Branch::create(['center_id' => $center->id, 'name' => 'فرع الإشعارات']);
    $circle = Circle::create(['branch_id' => $branch->id, 'name' => 'حلقة الإشعارات', 'type' => 'بنين', 'level' => 'مبتدئ']);

    $this->student1 = Student::create([
        'name' => 'طالب مُنبَّه', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $circle->id, 'guardian_id' => $this->guardian1->id,
        'center_id' => $center->id,
    ]);
    $this->student2 = Student::create([
        'name' => 'طالب هادئ', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $circle->id, 'guardian_id' => $this->guardian2->id,
        'center_id' => $center->id,
    ]);
});

test('manual absence notify reaches the guardian inbox exactly once per day', function () {
    $this->actingAs($this->admin)
        ->postJson(route('attendance.sequential-absences.notify', $this->student1))
        ->assertOk();

    $this->assertDatabaseHas('notifications', [
        'notifiable_id'   => $this->guardian1->id,
        'notifiable_type' => User::class,
        'type'            => SequentialAbsenceNotification::class,
    ]);

    // ولي الأمر يرى التنبيه في صندوق إشعاراته
    $this->actingAs($this->guardian1)
        ->get(route('guardian.notifications.index'))
        ->assertOk()
        ->assertSee($this->student1->name);

    // إعادة الإرسال في نفس اليوم تُرفض
    $this->actingAs($this->admin)
        ->postJson(route('attendance.sequential-absences.notify', $this->student1))
        ->assertStatus(409);
});

test('sequential absences command notifies only guardians at the threshold', function () {
    foreach (range(0, 4) as $daysAgo) {
        Attendance::create([
            'student_id' => $this->student1->id,
            'date'       => now()->subDays($daysAgo)->format('Y-m-d'),
            'status'     => 'absent',
            'user_id'    => $this->admin->id,
        ]);
    }
    foreach (range(0, 3) as $daysAgo) {
        Attendance::create([
            'student_id' => $this->student2->id,
            'date'       => now()->subDays($daysAgo)->format('Y-m-d'),
            'status'     => 'absent',
            'user_id'    => $this->admin->id,
        ]);
    }

    $this->artisan('notify:sequential-absences')->assertSuccessful();

    expect($this->guardian1->notifications()->count())->toBe(1)
        ->and($this->guardian1->notifications()->first()->type)->toBe(SequentialAbsenceNotification::class)
        ->and($this->guardian2->notifications()->count())->toBe(0);
});

test('unpaid subscriptions command notifies guardians with unpaid months', function () {
    (new StudentUnpaidMonths)->forceFill([
        'student_id'          => $this->student1->id,
        'unpaid_months_count' => 2,
    ])->save();

    // سجل انتساب للحلقة منذ شهرين دون أي اشتراك مدفوع — مصدر الحقيقة للأمر
    CircleAssignmentHistory::create([
        'student_id' => $this->student1->id,
        'circle_id'  => $this->student1->circle_id,
        'from_date'  => now()->subMonths(2)->startOfMonth()->format('Y-m-d'),
        'to_date'    => null,
    ]);

    $this->artisan('notify:unpaid-subscriptions', ['--force' => true])->assertSuccessful();

    expect($this->guardian1->notifications()->count())->toBe(1)
        ->and($this->guardian1->notifications()->first()->type)->toBe(UnpaidSubscriptionNotification::class)
        ->and($this->guardian2->notifications()->count())->toBe(0);
});

test('manual unpaid notify reaches the guardian', function () {
    (new StudentUnpaidMonths)->forceFill([
        'student_id'          => $this->student1->id,
        'unpaid_months_count' => 1,
    ])->save();

    $this->actingAs($this->admin)
        ->post(route('subscriptions.notify-unpaid', $this->student1));

    $this->assertDatabaseHas('notifications', [
        'notifiable_id'   => $this->guardian1->id,
        'notifiable_type' => User::class,
        'type'            => UnpaidSubscriptionNotification::class,
    ]);
});