<?php

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SequentialAbsenceNotification;
use Database\Seeders\PermissionSeeder;

/**
 * فحص نظام الإشعارات (تقرير 206): سلامة صفحة إشعارات ولي الأمر من تسريب
 * إشعارات الغير، ودقة فحص «تم التنبيه اليوم» — كان يطابق رقم الطالب بـ LIKE
 * على JSON فتتصادم البادئات (طالب 1 يُحجب لأن طالب 10 نُبّه اليوم).
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function notifAuditUser(string $role): User
{
    $u = User::create(['name' => $role, 'email' => $role . rand(1, 999999) . '@t.test', 'password' => bcrypt('x'), 'status' => 'active']);
    $u->assignRole($role);
    return $u;
}

function notifAuditStudent(int $centerId, int $circleId, int $guardianId, string $name): Student
{
    return Student::create([
        'name' => $name, 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $centerId, 'circle_id' => $circleId, 'guardian_id' => $guardianId,
    ]);
}

test('a guardian cannot read or mark another guardian notification', function () {
    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    $circle = Circle::create(['name' => 'حلقة', 'branch_id' => $branch->id, 'type' => 'group', 'level' => 'build', 'status' => 'active']);
    $a = notifAuditUser('guardian');
    $b = notifAuditUser('guardian');
    $sa = notifAuditStudent($center->id, $circle->id, $a->id, 'طالب الأول');
    $sb = notifAuditStudent($center->id, $circle->id, $b->id, 'طالب الثاني');
    $a->notify(new SequentialAbsenceNotification($sa, 6, null));
    $b->notify(new SequentialAbsenceNotification($sb, 8, null));

    // صفحة إشعاراته فيها طالب ابنه فقط
    $this->actingAs($a)->get(route('guardian.notifications.index'))
        ->assertOk()->assertSee('طالب الأول')->assertDontSee('طالب الثاني');

    // تعليم إشعار غيره كمقروء: 404 ويبقى غير مقروء
    $bNotif = $b->notifications()->first();
    $this->actingAs($a)->patchJson(route('guardian.notifications.read', $bNotif->id))->assertNotFound();
    expect($b->notifications()->first()->read_at)->toBeNull();
});

test('daily dedup matches the exact student, not an id prefix', function () {
    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    $circle = Circle::create(['name' => 'حلقة', 'branch_id' => $branch->id, 'type' => 'group', 'level' => 'build', 'status' => 'active']);
    $guardian = notifAuditUser('guardian');
    $students = [];
    for ($k = 1; $k <= 10; $k++) {
        $students[$k] = notifAuditStudent($center->id, $circle->id, $guardian->id, 'طالب ' . $k);
    }
    // نُبّه اليوم عن الطالب 10
    $guardian->notify(new SequentialAbsenceNotification($students[10], 7, null));

    $admin = notifAuditUser('admin');
    // الطالب 1 مختلف تمامًا: يجب أن ينجح تنبيهه (كان يُرفض 409 قبل الإصلاح)
    $this->actingAs($admin)->postJson(route('attendance.sequential-absences.notify', $students[1]), ['message' => 'تنبيه'])
        ->assertOk();
    // والطالب 10 نفسه: يُرفض فعلًا لأنه نُبّه اليوم
    $this->actingAs($admin)->postJson(route('attendance.sequential-absences.notify', $students[10]), ['message' => 'تنبيه'])
        ->assertStatus(409);
});

test('no sender uses the imprecise LIKE dedup anymore', function () {
    foreach ([
        app_path('Http/Controllers/AttendanceController.php'),
        app_path('Http/Controllers/SubscriptionController.php'),
        app_path('Console/Commands/NotifyUnpaidSubscriptions.php'),
    ] as $file) {
        expect(file_get_contents($file))->not->toContain("'data', 'like'");
    }
});

/**
 * حذف صلاحية view notifications (تقرير 207): كانت استخدامها الوحيد إظهار
 * رابط القائمة الجانبية، والصفحة محمية أصلًا بدور ولي الأمر — فصار الرابط
 * مربوطًا بالدور كبقية روابط قسم ولي الأمر، والصلاحية محذوفة من الكتالوج.
 */
test('view notifications permission is deleted and the sidebar link follows the guardian role', function () {
    expect(\Spatie\Permission\Models\Permission::where('name', 'view notifications')->exists())->toBeFalse();

    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    $circle = Circle::create(['name' => 'حلقة', 'branch_id' => $branch->id, 'type' => 'group', 'level' => 'build', 'status' => 'active']);
    $guardian = notifAuditUser('guardian');
    $student = notifAuditStudent($center->id, $circle->id, $guardian->id, 'طالب الرابط');

    // ولي الأمر يرى الرابط ويفتح الصفحة
    $this->actingAs($guardian)->get(route('guardian.notifications.index'))
        ->assertOk()->assertSee(route('guardian.notifications.index'), false);

    // الأدمن (كان حاملًا للصلاحية المحذوفة) لا يرى الرابط الميت أصلًا
    $this->actingAs(notifAuditUser('admin'))->get(route('dashboard'))
        ->assertOk()->assertDontSee(route('guardian.notifications.index'), false);
});
