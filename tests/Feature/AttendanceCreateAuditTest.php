<?php
use App\Models\{Attendance, Branch, Center, Circle, Student, Teacher, User};
use Illuminate\Support\Facades\DB;
use Database\Seeders\PermissionSeeder;

/**
 * فحص إنشاء الحضور (تقرير 218): إعادة تسجيل يوم محذوف ناعمًا كانت تُحدِّث
 * الصف المحذوف فيبقى غير مرئي للأبد، والتواريخ غير القياسية كانت تُخزَّن
 * حرفيًا فلا تجدها الاستعلامات، ورسالة النجاح كانت تظهر حتى عند حفظ صفر سجل.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $mk = function (string $role, string $tag) {
        $u = User::create(['name' => $tag, 'email' => $tag . '@t.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->assignRole($role);
        return $u;
    };
    $center = Center::create(['name' => 'مركز']);
    $branch = Branch::create(['name' => 'فرع', 'center_id' => $center->id]);
    $this->circle = Circle::create(['name' => 'حلقة', 'branch_id' => $branch->id, 'type' => 'group', 'level' => 'build', 'status' => 'active']);
    $otherCircle = Circle::create(['name' => 'حلقة أخرى', 'branch_id' => $branch->id, 'type' => 'group', 'level' => 'build', 'status' => 'active']);
    $this->teacher = $mk('teacher', 'المعلم');
    $t = Teacher::create(['user_id' => $this->teacher->id, 'branch_id' => $branch->id, 'name' => 'المعلم']);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circle->id, 'teacher_id' => $t->id, 'role' => 'main']);
    $this->student = Student::create(['name' => 'طالب', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول', 'center_id' => $center->id, 'circle_id' => $this->circle->id]);
    $this->foreign = Student::create(['name' => 'طالب أجنبي', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول', 'center_id' => $center->id, 'circle_id' => $otherCircle->id]);
});

test('re-registering a soft deleted day restores the record', function () {
    $payload = ['circle_id' => $this->circle->id, 'date' => '2026-11-20', 'attendance' => [['student_id' => $this->student->id, 'status' => 'present']]];
    $this->actingAs($this->teacher)->post(route('attendance.store'), $payload)->assertSessionHas('success');
    expect(Attendance::where('student_id', $this->student->id)->count())->toBe(1);

    Attendance::first()->delete(); // حذف ناعم كما يفعل destroy
    expect(Attendance::where('student_id', $this->student->id)->count())->toBe(0);

    $payload['attendance'][0]['status'] = 'absent';
    $this->actingAs($this->teacher)->post(route('attendance.store'), $payload)->assertSessionHas('success');
    $rows = Attendance::where('student_id', $this->student->id)->get();
    expect($rows)->toHaveCount(1)->and($rows->first()->status->value)->toBe('absent');
});

test('non iso dates are normalized before storage', function () {
    $this->actingAs($this->teacher)->post(route('attendance.store'), [
        'circle_id' => $this->circle->id, 'date' => '20-11-2026',
        'attendance' => [['student_id' => $this->student->id, 'status' => 'present']],
    ])->assertSessionHas('success');
    expect(Attendance::where('date', '2026-11-20')->count())->toBe(1);
});

test('no false success when nothing is saved', function () {
    $this->actingAs($this->teacher)->post(route('attendance.store'), [
        'circle_id' => $this->circle->id, 'date' => '2026-11-20',
        'attendance' => [['student_id' => $this->foreign->id, 'status' => 'present']],
    ])->assertSessionHas('error')->assertSessionMissing('success');
    expect(Attendance::count())->toBe(0);
});
