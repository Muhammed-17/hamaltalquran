<?php

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Competition;
use App\Models\CompetitionExaminer;
use App\Models\Examiner;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\SequentialAbsenceNotification;
use Database\Seeders\PermissionSeeder;

/*
|--------------------------------------------------------------------------
| اختبارات تعدد الفاعلين — عزل النطاقات بين الأدوار
|--------------------------------------------------------------------------
| كل فاعل (ولي أمر / معلم / مشرف / ممتحِن) يجب أن يرى بيانات نطاقه فقط،
| وأن يُمنع من الوصول المباشر لبيانات خارج نطاقه.
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $makeUser = function (string $role) {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    };

    $this->guardian1 = $makeUser('guardian');
    $this->guardian2 = $makeUser('guardian');

    $center = Center::create(['name' => 'مركز الفاعلين']);
    $this->branch1 = Branch::create(['center_id' => $center->id, 'name' => 'فرع أول']);
    $this->branch2 = Branch::create(['center_id' => $center->id, 'name' => 'فرع ثانٍ']);
    $this->circle1 = Circle::create(['branch_id' => $this->branch1->id, 'name' => 'حلقة أولى', 'type' => 'بنين', 'level' => 'مبتدئ']);
    $this->circle2 = Circle::create(['branch_id' => $this->branch2->id, 'name' => 'حلقة ثانية', 'type' => 'بنين', 'level' => 'مبتدئ']);

    $this->student1 = Student::create([
        'name' => 'ابن الولي الأول', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $this->circle1->id, 'guardian_id' => $this->guardian1->id,
        'center_id' => $center->id,
    ]);
    $this->student2 = Student::create([
        'name' => 'ابن الولي الثاني', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $this->circle2->id, 'guardian_id' => $this->guardian2->id,
        'center_id' => $center->id,
    ]);

    // معلم لكل حلقة عبر جدول الربط بدور رئيسي — وسجل المعلم مرتبط بفرع
    // (بدون الفرع يعتبره UserAccessService بلا مركز فتُحجب كل الحلقات)
    $this->teacherUser1 = $makeUser('teacher');
    $teacher1 = Teacher::create(['user_id' => $this->teacherUser1->id]);
    $teacher1->branch()->associate($this->branch1)->save();
    $teacher1->circles()->attach($this->circle1->id, ['role' => 'main']);

    $teacherUser2 = $makeUser('teacher');
    $teacher2 = Teacher::create(['user_id' => $teacherUser2->id]);
    $teacher2->branch()->associate($this->branch2)->save();
    $teacher2->circles()->attach($this->circle2->id, ['role' => 'main']);

    // مشرف على الفرع الأول فقط
    $this->supervisor = $makeUser('supervisor');
    $supervisorTeacher = Teacher::create(['user_id' => $this->supervisor->id]);
    $supervisorTeacher->branch()->associate($this->branch1)->save();
    $this->branch1->supervisors()->attach($supervisorTeacher->id);

    // ممتحِن
    $this->examinerUser = $makeUser('examiner');
    $this->examiner = Examiner::create(['user_id' => $this->examinerUser->id]);
});

test('guardian dashboard counts only own children and absences', function () {
    Attendance::create([
        'student_id' => $this->student2->id,
        'date'       => now()->format('Y-m-d'),
        'status'     => 'absent',
        'user_id'    => $this->guardian1->id,
    ]);

    $response = $this->actingAs($this->guardian1)->get(route('guardian.dashboard.own'));

    $response->assertOk();
    $response->assertViewHas('totalChildrenCount', 1);
    $response->assertViewHas('latestAbsences', fn ($absences) => count($absences) === 0);
});

test('guardian cannot mark another guardian notification as read', function () {
    $this->guardian2->notify(new SequentialAbsenceNotification($this->student2, 5));
    $notificationId = $this->guardian2->notifications()->first()->id;

    $this->actingAs($this->guardian1)
        ->patch(route('guardian.notifications.read', $notificationId))
        ->assertNotFound();

    $this->actingAs($this->guardian2)
        ->patch(route('guardian.notifications.read', $notificationId))
        ->assertOk();

    expect($this->guardian2->notifications()->first()->read_at)->not->toBeNull();
});

test('teacher student list contains only own circle students', function () {
    $response = $this->actingAs($this->teacherUser1)->get('/students');

    $response->assertOk();
    $ids = $response->viewData('students')->getCollection()->pluck('id');
    expect($ids)->toContain($this->student1->id)
        ->and($ids)->not->toContain($this->student2->id);
});

test('teacher cannot open a student from another circle directly', function () {
    $this->actingAs($this->teacherUser1)
        ->get('/students/' . $this->student1->id)
        ->assertOk();

    $this->actingAs($this->teacherUser1)
        ->get('/students/' . $this->student2->id)
        ->assertStatus(302);
});

test('supervisor student list contains only own branch students', function () {
    $response = $this->actingAs($this->supervisor)->get('/students');

    $response->assertOk();
    $ids = $response->viewData('students')->getCollection()->pluck('id');
    expect($ids)->toContain($this->student1->id)
        ->and($ids)->not->toContain($this->student2->id);
});

test('examiner sees only assigned competitions and cannot open others', function () {
    $assigned = Competition::create(['name' => 'مسابقة مُسندة', 'status' => 'active']);
    $other = Competition::create(['name' => 'مسابقة أخرى', 'status' => 'active']);
    CompetitionExaminer::create(['competition_id' => $assigned->id, 'examiner_id' => $this->examiner->id]);

    $response = $this->actingAs($this->examinerUser)->get(route('examiner.competitions.index'));

    $response->assertOk();
    $ids = $response->viewData('competitions')->getCollection()->pluck('id');
    expect($ids)->toContain($assigned->id)
        ->and($ids)->not->toContain($other->id);

    $this->actingAs($this->examinerUser)
        ->get(route('examiner.competitions.levels', $other))
        ->assertNotFound();
});