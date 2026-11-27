<?php

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;

/**
 * تحويل ثلاث صلاحيات إلى بوابات دور الأدمن فقط (بقراره):
 * manage guardians + view reports + export data حُذفت من كتالوج الصلاحيات،
 * وأقسامها (حسابات أولياء الأمور، تقرير الحضور، تصدير الحضور) أصبحت role:admin.
 * إنشاء ولي أمر من داخل نموذج الطالب لا علاقة له بها ويبقى متاحًا لغير الأدمن.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function adminOnlyUser(string $role): User
{
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole($role);

    return $u;
}

test('the three permissions no longer exist after seeding', function () {
    expect(Permission::whereIn('name', ['manage guardians', 'view reports', 'export data'])->count())->toBe(0);
});

test('guardian accounts, attendance report and exports are admin-only now', function () {
    $gm = adminOnlyUser('general_manager');
    $manager = adminOnlyUser('manager');
    $admin = adminOnlyUser('admin');

    foreach ([$gm, $manager] as $nonAdmin) {
        $this->actingAs($nonAdmin)->get(route('guardians.index'))->assertRedirect();
        $this->actingAs($nonAdmin)->get(route('attendance.report'))->assertRedirect();
        $this->actingAs($nonAdmin)->get(route('attendance.export.excel'))->assertRedirect();
    }

    $this->actingAs($admin)->get(route('guardians.index'))->assertOk();
    $this->actingAs($admin)->get(route('attendance.report'))->assertOk();
});

// كان المنشئ مدير مركز وقت كتابة الاختبار؛ إنشاء الطلاب صار للأدمن فقط بقرار لاحق فصار هو المنشئ هنا
test('a guardian can still be created inline from the student form', function () {
    $center = Center::create(['name' => 'مركز الاختبار']);
    $branch = Branch::create(['center_id' => $center->id, 'name' => 'فرع الاختبار']);
    Circle::create(['branch_id' => $branch->id, 'name' => 'حلقة الاختبار', 'type' => 'group', 'level' => 'build']);
    $supervisor = Teacher::create(['user_id' => User::factory()->create(['status' => 'active'])->id, 'branch_id' => $branch->id]);

    $manager = adminOnlyUser('admin');

    $response = $this->actingAs($manager)->post(route('students.store'), [
        'supervisor_id'     => $supervisor->id,
        'join_date'         => now()->format('Y-m-d'),
        'applicant'         => 'الأب',
        'name'              => 'طالب بولي جديد',
        'gender'            => 'ذكر',
        'address'           => 'عنوان اختباري',
        'center_id'         => $center->id,
        'guardian_id'       => 'new',
        'guardian_name'     => 'ولي أمر جديد',
        'parent_email'      => 'new.guardian@test.local',
        'password'          => 'password123',
        'educational_stage' => 'ابتدائي',
        'education_type'    => 'حكومي',
        'school_grade'      => 'الرابع',
        'previous_school'   => 'مدرسة سابقة',
        'previous_licenses_and_chains' => 'إجازة سابقة',
        'desired_narration_and_path'   => 'حفص عن عاصم',
        'health_status'         => 'سليم',
        'learning_difficulties' => 'لا يوجد',
        'personal_traits'       => 'هادئ',
        'student_exit_status'   => 'منتظم',
        'reading'                      => 'جيدة',
        'center_entry_level'           => 'creativity',
        'preferred_time_creativity'    => 'مسائي',
        'status'            => 'مقيد',
        'decision'          => 'مقبول',
        'subscription_fees' => 'مدفوع',
        'received_tools'    => 'تم الاستلام',
    ]);

    $response->assertRedirect(route('students.index'));
    $guardian = User::where('email', 'new.guardian@test.local')->first();
    expect($guardian)->not->toBeNull()
        ->and($guardian->hasRole('guardian'))->toBeTrue()
        ->and(Student::withoutGlobalScopes()->where('name', 'طالب بولي جديد')->first()?->guardian_id)->toBe($guardian->id);
});

/**
 * حذف مجموعة صلاحيات المستخدمين بالكامل (قراره، تابع لتحويل 182):
 * view/create/edit/delete users + manage roles لم تعد موجودة؛ صفحة الأدوار
 * محمية بدور الأدمن (مساراتها أصلًا role:admin)، وإنشاء المعلم يعمل
 * ببوابة create teachers وحدها بعد حذف بوابة UserPolicy الزائدة.
 */
test('the users permission group no longer exists after seeding', function () {
    expect(Permission::whereIn('name', ['view users', 'create users', 'edit users', 'delete users', 'manage roles'])->count())->toBe(0);
});

test('the roles page is admin-only and saving permissions still works', function () {
    $gm = adminOnlyUser('general_manager');
    $admin = adminOnlyUser('admin');

    $this->actingAs($gm)->get(route('admin.roles.index'))->assertRedirect();
    $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk();

    // الأدمن ما زال يقدر يحفظ صلاحيات دور من الصفحة بعد حذف قفل manage roles
    $teacherRole = \Spatie\Permission\Models\Role::findByName('teacher');
    $keepIds = $teacherRole->permissions->pluck('id')->toArray();
    $this->actingAs($admin)
        ->put(route('admin.roles.permissions.update', $teacherRole), ['permissions' => $keepIds])
        ->assertRedirect(route('admin.roles.index'));
    expect($teacherRole->fresh()->permissions->pluck('id')->toArray())->toEqualCanonicalizing($keepIds);
});

test('teacher account creation still works without the create users gate', function () {
    // إنشاء المعلمين للأدمن وحده في التوزيع الحالي، وبوابته الآن create teachers فقط
    $center = Center::create(['name' => 'مركز الاختبار']);
    $branch = Branch::create(['center_id' => $center->id, 'name' => 'فرع الاختبار']);

    $admin = adminOnlyUser('admin');

    $response = $this->actingAs($admin)->post(route('teachers.store'), [
        'name'      => 'معلم جديد',
        'email'     => 'new.teacher@test.local',
        'password'  => 'password123',
        'branch_id' => $branch->id,
        'roles'     => ['teacher'],
    ]);

    $response->assertRedirect(route('teachers.index'));
    $created = User::where('email', 'new.teacher@test.local')->first();
    expect($created)->not->toBeNull()
        ->and($created->hasRole('teacher'))->toBeTrue()
        ->and(Teacher::where('user_id', $created->id)->exists())->toBeTrue();
});

/**
 * حذف مجموعة صلاحيات المعلمين كلها (قراره، تابع لـ 182/183):
 * قسم المعلمين أصبح role:admin في المسارات والسياسة والواجهات،
 * والمدير العام ومدير المركز فقدا حتى عرض القائمة الذي كان لهما.
 */
test('the teachers permission group no longer exists after seeding', function () {
    expect(Permission::whereIn('name', ['view teachers', 'create teachers', 'edit teachers', 'delete teachers', 'toggle teacher status'])->count())->toBe(0);
});

test('the teachers section is admin-only now', function () {
    $gm = adminOnlyUser('general_manager');
    $manager = adminOnlyUser('manager');
    $admin = adminOnlyUser('admin');

    $this->actingAs($gm)->get(route('teachers.index'))->assertRedirect();
    $this->actingAs($manager)->get(route('teachers.index'))->assertRedirect();
    $this->actingAs($gm)->post(route('teachers.store'), [])->assertRedirect();
    $this->actingAs($admin)->get(route('teachers.index'))->assertOk();
});

/**
 * حلقات: الإنشاء والحذف للأدمن فقط (حُذفت صلاحيتاهما)، والتعديل لغير الأدمن
 * (مشرف/مدير/مدير عام) مقصور على اختيار المعلم الرئيسي والمساعد في نطاقه —
 * أي حقول أخرى في الطلب تُتجاهل، والأدمن يعدّل كل شيء (قراره).
 */
function circleFixture(): array
{
    $center = Center::create(['name' => 'مركز الحلقات']);
    $branch = Branch::create(['center_id' => $center->id, 'name' => 'فرع الحلقات']);
    $circle = Circle::create(['branch_id' => $branch->id, 'name' => 'حلقة الأصل', 'type' => 'group', 'level' => 'build']);
    $t1user = User::factory()->create(['status' => 'active']);
    $t1user->assignRole('teacher');
    $t2user = User::factory()->create(['status' => 'active']);
    $t2user->assignRole('teacher');
    $t1 = Teacher::create(['user_id' => $t1user->id, 'branch_id' => $branch->id]);
    $t2 = Teacher::create(['user_id' => $t2user->id, 'branch_id' => $branch->id]);
    $circle->teachers()->attach($t1->id, ['role' => 'main']);

    return [$center, $branch, $circle, $t1, $t2];
}

test('create and delete circle permissions no longer exist after seeding', function () {
    expect(Permission::whereIn('name', ['create circles', 'delete circles'])->count())->toBe(0);
});

test('circle create and delete are admin-only', function () {
    [$center, $branch, $circle] = circleFixture();
    $gm = adminOnlyUser('general_manager');
    $admin = adminOnlyUser('admin');

    $this->actingAs($gm)->delete(route('circles.destroy', $circle))->assertRedirect();
    expect(Circle::withoutGlobalScopes()->find($circle->id))->not->toBeNull();

    $this->actingAs($admin)->delete(route('circles.destroy', $circle))->assertRedirect(route('circles.index'));
    expect(Circle::withoutGlobalScopes()->find($circle->id))->toBeNull();
});

test('a manager edits only the teachers of his center circle, other fields are ignored', function () {
    [$center, $branch, $circle, $t1, $t2] = circleFixture();
    $manager = adminOnlyUser('manager');
    Teacher::create(['user_id' => $manager->id, 'branch_id' => $branch->id]);

    $response = $this->actingAs($manager)->put(route('circles.update', $circle), [
        'name'       => 'اسم مخترق',
        'url'        => 'https://example.com/hacked',
        'type'       => 'individual',
        'level'      => 'mastery',
        'branch_id'  => $branch->id,
        'teacher_id' => $t2->id,
        'assistant_teacher_id' => $t1->id,
    ]);

    $response->assertRedirect(route('circles.index'));
    $fresh = Circle::withoutGlobalScopes()->find($circle->id);
    expect($fresh->name)->toBe('حلقة الأصل')
        ->and($fresh->level)->toBe('build')
        ->and($fresh->mainTeachers->first()?->id)->toBe($t2->id)
        ->and($fresh->assistantTeachers->first()?->id)->toBe($t1->id);
});

test('an admin edits every circle field', function () {
    [$center, $branch, $circle, $t1, $t2] = circleFixture();
    $admin = adminOnlyUser('admin');

    $this->actingAs($admin)->put(route('circles.update', $circle), [
        'name'       => 'حلقة معدّلة',
        'type'       => 'group',
        'level'      => 'mastery',
        'branch_id'  => $branch->id,
        'teacher_id' => $t2->id,
    ])->assertRedirect(route('circles.index'));

    $fresh = Circle::withoutGlobalScopes()->find($circle->id);
    expect($fresh->name)->toBe('حلقة معدّلة')
        ->and($fresh->level)->toBe('mastery')
        ->and($fresh->mainTeachers->first()?->id)->toBe($t2->id);
});

test('a branch supervisor edits only the teachers, an outside supervisor cannot', function () {
    [$center, $branch, $circle, $t1, $t2] = circleFixture();

    $supervisor = adminOnlyUser('supervisor');
    $supTeacher = Teacher::create(['user_id' => $supervisor->id, 'branch_id' => $branch->id]);
    $branch->supervisors()->attach($supTeacher->id);
    // نطاق المشرف في اختيار المعلمين = معلمو حلقاته المشرف عليها (تصميم الوصول القائم)
    $circle->teachers()->attach($t2->id, ['role' => 'assistant']);

    $this->actingAs($supervisor)->put(route('circles.update', $circle), [
        'name'       => 'اسم مخترق',
        'branch_id'  => $branch->id,
        'teacher_id' => $t2->id,
        'assistant_teacher_id' => $t1->id,
    ])->assertRedirect(route('circles.index'));

    $fresh = Circle::withoutGlobalScopes()->find($circle->id);
    expect($fresh->name)->toBe('حلقة الأصل')
        ->and($fresh->mainTeachers->first()?->id)->toBe($t2->id)
        ->and($fresh->assistantTeachers->first()?->id)->toBe($t1->id);

    // مشرف فرع آخر: لا يصل للحلقة أصلًا فلا يتغير معلمها
    $branch2 = Branch::create(['center_id' => $center->id, 'name' => 'فرع آخر']);
    $outsider = adminOnlyUser('supervisor');
    $outTeacher = Teacher::create(['user_id' => $outsider->id, 'branch_id' => $branch2->id]);
    $branch2->supervisors()->attach($outTeacher->id);

    $this->actingAs($outsider)->put(route('circles.update', $circle), [
        'branch_id'  => $branch->id,
        'teacher_id' => $t1->id,
        'assistant_teacher_id' => $t2->id,
    ]);
    expect(Circle::withoutGlobalScopes()->find($circle->id)->mainTeachers->first()?->id)->toBe($t2->id);
});

/**
 * زرا «إضافة حصة» و«إضافة خطة الحلقة» للأدمن فقط (قراره):
 * إنشاء خطط الجلسات الجماعية أصبح role:admin وحُذفت صلاحيته، وإنشاء خطة
 * البناء في سياق الحلقة الجماعية للأدمن — بينما خطة الطالب الفردي بقيت
 * بصلاحية create students كما هي.
 */
test('create group session plans permission no longer exists', function () {
    expect(Permission::where('name', 'create group session plans')->exists())->toBeFalse();
});

/**
 * view circles حُذفت كصلاحية بقراره (المستخدم لن يفهمها ولن تتغير) وصار
 * دخول الحلقات بوابة أدوار ثابتة في الكود: الأدوار الخمسة (أدمن، مدير
 * عام، مدير فرع، مشرف، معلم) تدخل القائمة والتفاصيل كلٌّ في نطاقه،
 * وولي الأمر والممتحن ممنوعان.
 */
test('circles are a fixed role gate now: five roles enter, guardian cannot', function () {
    expect(Permission::where('name', 'view circles')->exists())->toBeFalse();

    [$center, $branch, $circle, $t1, $t2] = circleFixture();

    foreach (['admin', 'general_manager', 'manager', 'supervisor'] as $role) {
        $this->actingAs(adminOnlyUser($role))->get(route('circles.index'))->assertOk();
    }
    // المعلم: حسابه مربوط بسجل المعلم الرئيسي للحلقة
    $this->actingAs($t1->user)->get(route('circles.index'))->assertOk();
    $this->actingAs($t1->user)->get(route('circles.show', $circle))->assertOk();

    $guardian = adminOnlyUser('guardian');
    $this->actingAs($guardian)->get(route('circles.index'))->assertRedirect();
});

test('adding a group session is admin-only', function () {
    [$center, $branch, $circle] = circleFixture();
    $gm = adminOnlyUser('general_manager');
    $admin = adminOnlyUser('admin');

    $payload = [
        'circle_id'       => $circle->id,
        'session_name'    => 'حصة تجريبية',
        'start_time'      => '16:00',
        'end_time'        => '17:00',
        'planned_content' => 'محتوى مخطط',
    ];

    $this->actingAs($gm)->post(route('group-session-plans.store'), $payload)->assertRedirect();
    expect(\App\Models\GroupSessionPlan::count())->toBe(0);

    $this->actingAs($admin)->post(route('group-session-plans.store'), $payload)
        ->assertRedirect(route('circles.show', $circle));
    expect(\App\Models\GroupSessionPlan::count())->toBe(1);
});

test('circle construction plan creation is admin-only, student plan still works for staff', function () {
    [$center, $branch, $circle, $t1] = circleFixture();
    $manager = adminOnlyUser('manager');
    Teacher::create(['user_id' => $manager->id, 'branch_id' => $branch->id]);
    $admin = adminOnlyUser('admin');

    $planPayload = [
        'study_system'          => 'group',
        'new_memorization_plan' => 'حفظ جديد',
        'revision_plan'         => 'مراجعة',
        'old_memorization_plan' => 'قديم',
    ];

    // سياق الحلقة: المدير ممنوع، الأدمن يقدر
    $this->actingAs($manager)->post(route('student-construction-details.store'), $planPayload + ['circle_id' => $circle->id])
        ->assertRedirect();
    expect(\App\Models\StudentConstructionDetail::where('circle_id', $circle->id)->exists())->toBeFalse();

    $this->actingAs($admin)->post(route('student-construction-details.store'), $planPayload + ['circle_id' => $circle->id])
        ->assertRedirect(route('circles.show', $circle));
    expect(\App\Models\StudentConstructionDetail::where('circle_id', $circle->id)->whereNull('student_id')->exists())->toBeTrue();

    // سياق الطالب الفردي: كان للمدير بصلاحية create students، ثم صار للأدمن فقط بقرار تشديد لاحق
    $individual = Circle::create(['name' => 'حلقة فردية', 'branch_id' => $branch->id, 'type' => 'individual', 'level' => 'build']);
    $student = Student::create([
        'name' => 'طالب فردي', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $center->id, 'supervisor_id' => $t1->id, 'circle_id' => $individual->id,
    ]);
    $studentPayload = [
        'student_id'            => $student->id,
        'study_system'          => 'individual',
        'new_memorization_plan' => 'حفظ جديد',
        'revision_plan'         => 'مراجعة',
        'old_memorization_plan' => 'قديم',
    ];
    $this->actingAs($manager)->post(route('student-construction-details.store'), $studentPayload);
    expect(\App\Models\StudentConstructionDetail::where('student_id', $student->id)->exists())->toBeFalse();
    $this->actingAs($admin)->post(route('student-construction-details.store'), $studentPayload);
    expect(\App\Models\StudentConstructionDetail::where('student_id', $student->id)->exists())->toBeTrue();
});

/**
 * حذف create/edit students وmanage student status كصلاحيات (قراره) وتحويلها
 * لبوابات أدوار ثابتة. (في 188 حُفظ التوزيع القديم، ثم شدّد صاحب الموقع
 * بقرار لاحق: الإنشاء والتعديل وإدارة الحالة للأدمن فقط.) أما
 * delete students فمستخدمة فعليًا وباقية كما هي بقراره بعد التحقق.
 */
test('student create edit and status permissions are deleted', function () {
    foreach (['create students', 'edit students', 'manage student status'] as $name) {
        expect(Permission::where('name', $name)->exists())->toBeFalse();
    }
});

test('student creation and editing are admin-only by his later order', function () {
    [$center, $branch, $circle] = circleFixture();
    $manager = adminOnlyUser('manager');
    Teacher::create(['user_id' => $manager->id, 'branch_id' => $branch->id]);

    // صفحتا الإنشاء والتعديل: الأدمن فقط — مدير المركز والمدير العام والمشرف ممنوعون
    $this->actingAs(adminOnlyUser('admin'))->get(route('students.create'))->assertOk();
    foreach ([$manager, adminOnlyUser('general_manager'), adminOnlyUser('supervisor')] as $nonAdmin) {
        $this->actingAs($nonAdmin)->get(route('students.create'))->assertRedirect();
    }

    $student = Student::create([
        'name' => 'طالب التعديل', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $center->id, 'circle_id' => $circle->id,
    ]);
    $this->actingAs(adminOnlyUser('admin'))->get(route('students.edit', $student))->assertOk();
    $this->actingAs($manager)->get(route('students.edit', $student))->assertRedirect();
});

/**
 * صلاحيتا create student و edit student (بقراره، بالمفرد كما كتبهما): أُضيفتا
 * للكتالوج وممنوحتان مبدئيًا للأدمن فقط — فيبقى الوصول الحالي كما هو — وبوابات
 * الإنشاء والتعديل (المسارات وسياسة الطلاب وسياق الطالب في خطط البناء) صارت
 * بهما بدل role:admin، فيستطيع منح أي دور من صفحة الأدوار فيعمل فورًا.
 * إدارة الحالة والحذف والتسكين بقيت للأدمن بالدور كما هي.
 */
test('create student and edit student permissions exist, are admin-only by default, and open the gates when granted', function () {
    foreach (['create student', 'edit student'] as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue();
    }
    expect(\Spatie\Permission\Models\Role::findByName('admin')->hasPermissionTo('create student'))->toBeTrue()
        ->and(\Spatie\Permission\Models\Role::findByName('admin')->hasPermissionTo('edit student'))->toBeTrue();
    foreach (['general_manager', 'manager', 'supervisor', 'teacher'] as $role) {
        expect(\Spatie\Permission\Models\Role::findByName($role)->hasPermissionTo('create student'))->toBeFalse()
            ->and(\Spatie\Permission\Models\Role::findByName($role)->hasPermissionTo('edit student'))->toBeFalse();
    }

    [$center, $branch, $circle] = circleFixture();
    $manager = adminOnlyUser('manager');
    Teacher::create(['user_id' => $manager->id, 'branch_id' => $branch->id]);
    $student = Student::create([
        'name' => 'طالب الصلاحيتين', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $center->id, 'circle_id' => $circle->id,
    ]);

    // بلا الصلاحيتين: مغلق كما كان بعد تشديد الأدمن فقط
    $this->actingAs($manager)->get(route('students.create'))->assertRedirect();
    $this->actingAs($manager)->get(route('students.edit', $student))->assertRedirect();

    // بمنحهما: الصفحتان تنفتحان فورًا
    $manager->givePermissionTo(['create student', 'edit student']);
    $this->actingAs($manager->fresh())->get(route('students.create'))->assertOk();
    $this->actingAs($manager->fresh())->get(route('students.edit', $student))->assertOk();

    // وسياق الطالب الفردي في خطط البناء يتبع create student، وسياق الحلقة يبقى للأدمن
    $individual = Circle::create(['name' => 'حلقة فردية للصلاحية', 'branch_id' => $branch->id, 'type' => 'individual', 'level' => 'build']);
    $planStudent = Student::create([
        'name' => 'طالب خطة الصلاحية', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $center->id, 'circle_id' => $individual->id,
    ]);
    $planPayload = [
        'study_system'          => 'individual',
        'new_memorization_plan' => 'حفظ جديد',
        'revision_plan'         => 'مراجعة',
        'old_memorization_plan' => 'قديم',
    ];
    $this->actingAs($manager->fresh())->post(route('student-construction-details.store'), $planPayload + ['student_id' => $planStudent->id]);
    expect(\App\Models\StudentConstructionDetail::where('student_id', $planStudent->id)->exists())->toBeTrue();

    $this->actingAs($manager->fresh())->post(route('student-construction-details.store'), $planPayload + ['circle_id' => $circle->id]);
    expect(\App\Models\StudentConstructionDetail::where('circle_id', $circle->id)->whereNull('student_id')->exists())->toBeFalse();
});

test('delete students is live: admin deletes a student, center manager cannot', function () {
    [$center, $branch, $circle] = circleFixture();
    $manager = adminOnlyUser('manager');
    Teacher::create(['user_id' => $manager->id, 'branch_id' => $branch->id]);
    $student = Student::create([
        'name' => 'طالب الحذف', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $center->id, 'circle_id' => $circle->id,
    ]);

    $this->actingAs($manager)->delete(route('students.destroy', $student))->assertRedirect();
    expect(Student::withoutGlobalScopes()->whereKey($student->id)->exists())->toBeTrue();

    $this->actingAs(adminOnlyUser('admin'))->delete(route('students.destroy', $student));
    expect(Student::withoutGlobalScopes()->whereKey($student->id)->exists())->toBeFalse();
});

/**
 * assign student to circle حُذفت كصلاحية بقراره (كانت خاملة عمليًا بعد أن
 * صار تعديل الطلاب للأدمن فقط، والأدمن كان حاملها أصلًا): فحصا النقل في
 * StudentController وسياسة assignCircle صارا hasRole('admin') — ونقل
 * الأدمن للطالب بين الحلقات ما زال يعمل ويفتح فترة التحاق جديدة.
 */
test('assign student to circle permission is deleted and admin circle moves still work', function () {
    expect(Permission::where('name', 'assign student to circle')->exists())->toBeFalse();

    [$center, $branch, $circle, $t1] = circleFixture();
    $other = Circle::create(['name' => 'حلقة ثانية', 'branch_id' => $branch->id, 'type' => 'group', 'level' => 'build']);
    $student = Student::create([
        'name' => 'طالب النقل', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $center->id, 'circle_id' => $circle->id, 'supervisor_id' => $t1->id,
        'join_date' => now()->format('Y-m-d'),
    ]);
    \App\Models\CircleAssignmentHistory::create([
        'student_id' => $student->id, 'circle_id' => $circle->id,
        'from_date' => now()->format('Y-m-d'), 'to_date' => null,
    ]);

    $this->actingAs(adminOnlyUser('admin'))->put(route('students.update', $student), [
        'supervisor_id' => $t1->id,
        'join_date' => now()->format('Y-m-d'),
        'applicant' => 'الأب',
        'name' => 'طالب النقل',
        'gender' => 'ذكر',
        'address' => 'عنوان',
        'center_id' => $center->id,
        'guardian_id' => 'none',
        'educational_stage' => 'ابتدائي',
        'education_type' => 'حكومي',
        'school_grade' => 'الرابع',
        'previous_school' => 'مدرسة سابقة',
        'health_status' => 'سليم',
        'learning_difficulties' => 'لا يوجد',
        'personal_traits' => 'هادئ',
        'student_exit_status' => 'منتظم',
        'reading' => 'جيدة',
        'center_entry_level' => 'construction',
        'study_system' => 'group',
        'circle_id' => $other->id,
        'status' => 'مقيد',
        'decision' => 'مقبول',
        'subscription_fees' => 'مدفوع',
        'received_tools' => 'تم الاستلام',
    ]);

    expect(Student::withoutGlobalScopes()->find($student->id)->circle_id)->toBe($other->id)
        ->and(\App\Models\CircleAssignmentHistory::where('student_id', $student->id)->where('circle_id', $other->id)->whereNull('to_date')->exists())->toBeTrue();
});

/**
 * زر حذف الطالب في صفحة الطلاب (طلبه صاحب الموقع بعد أن تبيّن أن الحذف
 * كان بلا أي زر في الواجهة): يظهر للأدمن وحده عبر سياسة الحذف، وبنفس
 * تأكيد SweetAlert المستخدم في الموقع، والحذف الفعلي يعمل من الصفحة.
 */
test('students index shows a delete button to admin only and it deletes', function () {
    [$center, $branch, $circle] = circleFixture();
    $student = Student::create([
        'name' => 'طالب زر الحذف', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $center->id, 'circle_id' => $circle->id,
    ]);
    $manager = adminOnlyUser('manager');
    Teacher::create(['user_id' => $manager->id, 'branch_id' => $branch->id]);

    $adminPage = $this->actingAs(adminOnlyUser('admin'))->get(route('students.index'));
    $adminPage->assertOk();
    $adminPage->assertSee('title="حذف الطالب"', false);

    $managerPage = $this->actingAs($manager)->get(route('students.index'));
    $managerPage->assertOk();
    $managerPage->assertDontSee('title="حذف الطالب"', false);
});

/**
 * حذف view students وdelete students كصلاحيات (قراره: تخفيف عدد الصلاحيات
 * على المستخدم): العرض صار بوابة الأدوار الخمسة الثابتة كما كان توزيعها،
 * والحذف صار للأدمن فقط بالدور — ومسار ولي الأمر لعرض أبنائه لم يتأثر.
 */
test('view and delete students permissions are deleted, access is role-gated as before', function () {
    expect(Permission::where('name', 'view students')->exists())->toBeFalse()
        ->and(Permission::where('name', 'delete students')->exists())->toBeFalse();

    [$center, $branch, $circle, $t1] = circleFixture();

    foreach (['admin', 'general_manager', 'manager', 'supervisor'] as $role) {
        $this->actingAs(adminOnlyUser($role))->get(route('students.index'))->assertOk();
    }
    $this->actingAs($t1->user)->get(route('students.index'))->assertOk();
    $this->actingAs(adminOnlyUser('guardian'))->get(route('students.index'))->assertRedirect();
});

test('guardian still opens his own child page after the view students conversion', function () {
    [$center, $branch, $circle] = circleFixture();
    $guardian = adminOnlyUser('guardian');
    $own = Student::create([
        'name' => 'ابن الولي', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $center->id, 'circle_id' => $circle->id, 'guardian_id' => $guardian->id,
    ]);
    $other = Student::create([
        'name' => 'طالب آخر', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $center->id, 'circle_id' => $circle->id,
    ]);

    $this->actingAs($guardian)->get(route('students.show', $own))->assertOk();
    $this->actingAs($guardian)->get(route('students.show', $other))->assertRedirect();
});

/**
 * حذف view attendance وview all teachers كصلاحيات (قراره) واستبدالهما
 * بأدوار: عرض الحضور للأدوار الخمسة كما كان، وفلتر المسجّل للأربعة
 * الإشرافيين (الأدمن والمدير العام ومدير المركز والمشرف — المعلم لم
 * يكن يملكه أصلًا).
 */
test('attendance view permissions are deleted and replaced by roles', function () {
    expect(Permission::where('name', 'view attendance')->exists())->toBeFalse()
        ->and(Permission::where('name', 'view all teachers')->exists())->toBeFalse();

    circleFixture();

    foreach (['admin', 'general_manager', 'manager', 'supervisor', 'teacher'] as $role) {
        $user = $role === 'teacher' ? null : adminOnlyUser($role);
        if ($user) {
            $this->actingAs($user)->get(route('attendance.index'))->assertOk();
        }
    }
    $this->actingAs(adminOnlyUser('guardian'))->get(route('attendance.index'))->assertRedirect();
});

/**
 * delete attendance حُذفت كصلاحية وصارت role:admin (قراره). السياسة كانت
 * أصلًا للأدمن فقط، فالسلوك الفعلي لم يتغير: المدير العام كان يمر من
 * بوابة المسار ثم ترفضه السياسة، وصار يُرفض من المسار مباشرة.
 */
test('delete attendance permission is deleted and only admin deletes records', function () {
    expect(Permission::where('name', 'delete attendance')->exists())->toBeFalse();

    [$center, $branch, $circle] = circleFixture();
    $student = Student::create([
        'name' => 'طالب حضور', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $center->id, 'circle_id' => $circle->id,
    ]);
    $record = \App\Models\Attendance::create([
        'student_id' => $student->id, 'date' => now()->format('Y-m-d'), 'status' => 'present',
    ]);

    $this->actingAs(adminOnlyUser('general_manager'))->delete(route('attendance.destroy', $record))->assertRedirect();
    expect(\App\Models\Attendance::whereKey($record->id)->exists())->toBeTrue();

    $this->actingAs(adminOnlyUser('admin'))->delete(route('attendance.destroy', $record));
    expect(\App\Models\Attendance::whereKey($record->id)->exists())->toBeFalse();
});



/**
 * صفحة تفاصيل سجل الحضور استُرجعت بقراره بعد حذفها في 195: المسار
 * والدالة والعرض وزر العين عادوا كما كانوا حرفيًا.
 */
test('attendance details page is restored and loads', function () {
    [$center, $branch, $circle] = circleFixture();
    $student = Student::create([
        'name' => 'طالب مسترجع', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $center->id, 'circle_id' => $circle->id,
    ]);
    $record = \App\Models\Attendance::create([
        'student_id' => $student->id, 'date' => now()->format('Y-m-d'), 'status' => 'present',
    ]);

    $page = $this->actingAs(adminOnlyUser('admin'))->get(route('attendance.show', $record));
    $page->assertOk();
    $page->assertSee('تفاصيل سجل الحضور');
});

/**
 * حذف create attendance وedit attendance كصلاحيات (قراره) واستبدالهما
 * بأدوار بنفس التوزيع: الإنشاء للأدوار الخمسة (المعلم يسجّل حضور حلقاته)،
 * والتعديل للأربعة الإشرافيين (المعلم لم يكن يعدّل أصلًا).
 */
test('create and edit attendance permissions are deleted and replaced by roles', function () {
    expect(Permission::where('name', 'create attendance')->exists())->toBeFalse()
        ->and(Permission::where('name', 'edit attendance')->exists())->toBeFalse();

    [$center, $branch, $circle, $t1] = circleFixture();

    // صفحة الإنشاء: بوابتها للأدوار الخمسة (الدخول بحلقة مختارة يعرض النموذج)
    $this->actingAs(adminOnlyUser('admin'))->get(route('attendance.create', ['circle_id' => $circle->id]))->assertOk();
    $this->actingAs($t1->user)->get(route('attendance.create', ['circle_id' => $circle->id]))->assertOk();
    $this->actingAs(adminOnlyUser('guardian'))->get(route('attendance.create'))->assertRedirect();

    // بوابة التعديل على مستوى السياسة (بلا سجل): المعلم خارجها كما كان، والأربعة داخلها
    expect($t1->user->can('create', \App\Models\Attendance::class))->toBeTrue()
        ->and($t1->user->can('update', \App\Models\Attendance::class))->toBeFalse()
        ->and(adminOnlyUser('supervisor')->can('update', \App\Models\Attendance::class))->toBeTrue()
        ->and(adminOnlyUser('guardian')->can('create', \App\Models\Attendance::class))->toBeFalse();
});

/**
 * صفحة الغيابات المتكررة صارت لها صلاحية خاصة بها (قراره): view sequential
 * absences في كتالوج الحضور، ممنوحة مبدئيًا للأدوار الخمسة كما كان الوصول،
 * والبوابة صارت الصلاحية نفسها — سحبها من دور يقفل الصفحة عنه فورًا.
 */
test('sequential absences page has its own permission now', function () {
    expect(Permission::where('name', 'view sequential absences')->exists())->toBeTrue();
    foreach (['admin', 'general_manager', 'manager', 'supervisor', 'teacher'] as $role) {
        expect(\Spatie\Permission\Models\Role::findByName($role)->hasPermissionTo('view sequential absences'))->toBeTrue();
    }
    expect(\Spatie\Permission\Models\Role::findByName('guardian')->hasPermissionTo('view sequential absences'))->toBeFalse();

    circleFixture();
    // ملحوظة بيئية: استعلام الصفحة يستخدم HAVING على عدّاد فرعي — يعمل على
    // MariaDB (بيئة الموقع) ويرفضه SQLite، لذا يُثبَّت المرور من البوابة هنا
    // بعدم التحويل، ويُثبَّت القفل بالتحويل في حالة السحب بالأسفل.
    $status = $this->actingAs(adminOnlyUser('supervisor'))->get(route('attendance.sequential-absences'))->getStatusCode();
    expect($status)->not->toBe(302);

    // سحب الصلاحية من دور المعلم يقفل الصفحة عن المعلم رغم بقاء دوره
    \Spatie\Permission\Models\Role::findByName('teacher')->revokePermissionTo('view sequential absences');
    $teacherUser = adminOnlyUser('teacher');
    $this->actingAs($teacherUser)->get(route('attendance.sequential-absences'))->assertRedirect();
});

/**
 * مراجعة المستثناة وتصدير الطلاب للأدمن فقط (قراره)، وصفحة المفضلة صارت
 * لها صلاحية خاصة view favorites ممنوحة مبدئيًا للأدوار الخمسة — سحبها
 * من دور يقفل الصفحة عنه.
 */
test('excluded review and students export are admin only and favorites has its own permission', function () {
    expect(Permission::where('name', 'view favorites')->exists())->toBeTrue();
    foreach (['admin', 'general_manager', 'manager', 'supervisor', 'teacher'] as $role) {
        expect(\Spatie\Permission\Models\Role::findByName($role)->hasPermissionTo('view favorites'))->toBeTrue();
    }

    circleFixture();

    // التصدير والمراجعة: غير الأدمن يُرفض، والأدمن يدخل
    $this->actingAs(adminOnlyUser('manager'))->get(route('students.export'))->assertRedirect();
    $this->actingAs(adminOnlyUser('manager'))->get(route('students.excluded-review'))->assertRedirect();
    $this->actingAs(adminOnlyUser('admin'))->get(route('students.excluded-review'))->assertOk();
    $this->actingAs(adminOnlyUser('admin'))->get(route('students.export'))->assertOk();

    // المفضلة: المشرف يدخل بالصلاحية، وسحبها من المعلم يقفلها عنه
    $this->actingAs(adminOnlyUser('supervisor'))->get(route('favorites.index'))->assertOk();
    \Spatie\Permission\Models\Role::findByName('teacher')->revokePermissionTo('view favorites');
    $this->actingAs(adminOnlyUser('teacher'))->get(route('favorites.index'))->assertRedirect();
});

/**
 * view own attendance حُذفت كصلاحية (قراره): صفحة حضور الأبناء داخل منطقة
 * ولي الأمر المحكومة أصلًا بدور guardian، فصارت بوابتها الدور نفسه.
 */
test('view own attendance permission is deleted and replaced by the guardian role', function () {
    expect(Permission::where('name', 'view own attendance')->exists())->toBeFalse();

    $guardian = adminOnlyUser('guardian');
    $this->actingAs($guardian)->get(route('guardian.attendance.own'))->assertOk();
    $this->actingAs(adminOnlyUser('teacher'))->get(route('guardian.attendance.own'))->assertRedirect();
});

/**
 * view own subscriptions حُذفت كصلاحية (قراره): ولي الأمر يدخل صفحات
 * الاشتراكات بدوره — صفحة مدفوعات الأبناء وقائمة الاشتراكات مقصورة على
 * أبنائه بسياسة العرض كما كانت.
 */
test('view own subscriptions permission is deleted and replaced by the guardian role', function () {
    expect(Permission::where('name', 'view own subscriptions')->exists())->toBeFalse();

    [$center, $branch, $circle] = circleFixture();
    $guardian = adminOnlyUser('guardian');
    $own = Student::create([
        'name' => 'ابن ولي الأمر', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $center->id, 'circle_id' => $circle->id, 'guardian_id' => $guardian->id,
    ]);
    $sub = \App\Models\Subscription::create([
        'student_id' => $own->id, 'circle_id' => $circle->id, 'month' => now()->startOfMonth()->format('Y-m-d'),
        'amount' => 100, 'status' => 'مدفوع',
    ]);
    expect($guardian->can('view', $sub))->toBeTrue();

    $this->actingAs($guardian)->get(route('guardian.subscription.own'))->assertOk();
    // قائمة الاشتراكات العامة ترفض ولي الأمر أصلًا بسياسة viewAny (سلوك قديم لم يتغير)
    $this->actingAs($guardian)->get(route('subscriptions.index'))->assertRedirect();
    $this->actingAs(adminOnlyUser('teacher'))->get(route('guardian.subscription.own'))->assertRedirect();
});

/**
 * صفحة تسجيل الحضور صارت تُحمَّل جزئيًا (قراره): منطقة العمل لها معرّف
 * ثابت، ومعالجات التاريخ والمعلم والحلقة تستدعي الجلب الجزئي بدل التنقّل
 * الكامل، ودالة الجلب موجودة في الصفحة.
 */
test('attendance create page loads partially when filters change', function () {
    [$center, $branch, $circle, $t1] = circleFixture();

    $response = $this->actingAs($t1->user)->get(route('attendance.create', ['circle_id' => $circle->id]));
    $response->assertOk();
    $response->assertSee('id="attendance-workspace"', false);
    $response->assertSee('window.loadAttendancePartial', false);
    $response->assertDontSee("getElementById('attendanceFilterForm').submit()", false);
    $response->assertDontSee('window.location.href = url.toString()', false);
});

/**
 * إصلاح عطل التحميل الجزئي في تسجيل الحضور (تقرير صاحب الموقع): معالج
 * التاريخ الداخلي كان ينهار لأن document.URL يحجب مُنشئ URL داخل خصائص
 * الأحداث الداخلية، ومعالجا الحلقة/المعلم كانا يرسلان تاريخ أول تحميل.
 * الآن: مستمع مفوَّض للتاريخ، والحفاظ على تاريخ الرابط الحالي.
 */
test('attendance create date change uses a delegated listener not an inline handler', function () {
    [$center, $branch, $circle, $t1] = circleFixture();

    $response = $this->actingAs($t1->user)->get(route('attendance.create', ['circle_id' => $circle->id]));
    $response->assertOk();
    $response->assertDontSee('onchange="const u = new URL', false);
    $response->assertSee("e.target.matches('#attendance-workspace input[name=\"date\"]')", false);
    $response->assertSee("if (!url.searchParams.get('date'))", false);
});

/**
 * عمود «إرسال تنبيه» في صفحة الغيابات المتكررة صار مخفيًا عن غير حاملي
 * notify attendance (قراره): الزر كان ظاهرًا للخمسة والإرسال يرفض غير
 * الحاملين، فصار اللي شايفه هو اللي يقدر يستخدمه.
 */
test('sequential absences notify column is hidden from non notify holders', function () {
    $blade = file_get_contents(resource_path('views/attendance/sequential-absences.blade.php'));
    expect(substr_count($blade, "@can('notify attendance')"))->toBeGreaterThanOrEqual(2);

    [$center, $branch, $circle] = circleFixture();
    $guardian = adminOnlyUser('guardian');
    $student = Student::create([
        'name' => 'طالب غياب متكرر', 'gender' => 'ذكر', 'status' => 'مقيد', 'decision' => 'مقبول',
        'center_id' => $center->id, 'circle_id' => $circle->id, 'guardian_id' => $guardian->id,
    ]);

    // مشرف فعلي على الفرع (حتى يصل الطلب لبوابة الصلاحية لا لحجب النطاق)
    $supervisor = adminOnlyUser('supervisor');
    $supTeacher = \App\Models\Teacher::create(['user_id' => $supervisor->id, 'branch_id' => $branch->id, 'name' => 'مشرف الفرع']);
    \Illuminate\Support\Facades\DB::table('branch_teacher')->insert(['branch_id' => $branch->id, 'teacher_id' => $supTeacher->id]);

    // المشرف (بلا الصلاحية) يُرفض عند الإرسال، والأدمن (حاملها) ينجح
    $this->actingAs($supervisor)
        ->postJson(route('attendance.sequential-absences.notify', $student), ['message' => 'تنبيه'])
        ->assertForbidden();
    $this->actingAs(adminOnlyUser('admin'))
        ->postJson(route('attendance.sequential-absences.notify', $student), ['message' => 'تنبيه'])
        ->assertOk();
});

/**
 * دور ولي الأمر محذوف من صفحة الأدوار والصلاحيات (قراره، تقرير 209):
 * لا يظهر في القائمة، وتعديل صلاحياته بطلب مباشر مرفوض — وصوله مربوط
 * بالدور نفسه بعد حذف صلاحياته الخاصة (200–208).
 */
test('guardian role is not managed on the roles page', function () {
    $guardianRole = \Spatie\Permission\Models\Role::findByName('guardian');
    $teacherRole = \Spatie\Permission\Models\Role::findByName('teacher');
    $admin = adminOnlyUser('admin');

    $page = $this->actingAs($admin)->get(route('admin.roles.index'));
    $page->assertOk();
    $page->assertSee(route('admin.roles.permissions.update', $teacherRole->id), false);
    $page->assertDontSee(route('admin.roles.permissions.update', $guardianRole->id), false);

    // طلب مباشر لتغيير صلاحياته: يُرفض وتبقى صلاحياته كما هي (فارغة — تقرير 210)
    $somePermission = \Spatie\Permission\Models\Permission::where('name', 'notify attendance')->first();
    $this->actingAs($admin)->put(route('admin.roles.permissions.update', $guardianRole->id), [
        'permissions' => [$somePermission->id],
    ])->assertRedirect(route('admin.roles.index'));
    expect($guardianRole->fresh()->permissions->pluck('name')->all())->toBe([]);
});

/**
 * حذف edit profile من ولي الأمر (قراره الصريح، تقرير 210): ولي الأمر بلا
 * أي صلاحية الآن، وصفحة الملف الشخصي مقفولة عليه تمامًا (المسار محمي
 * بالصلاحية نفسها) — بينما بقية الأدوار الحاملة تصل لملفها كما هي.
 */
test('guardian has no permissions and cannot open the profile page', function () {
    $guardian = adminOnlyUser('guardian');
    expect($guardian->getAllPermissions()->pluck('name')->all())->toBe([]);
    $this->actingAs($guardian)->get(route('profile.edit'))->assertStatus(302);

    $teacher = adminOnlyUser('teacher');
    $this->actingAs($teacher)->get(route('profile.edit'))->assertOk();
});

/**
 * حذف صلاحيتي أسعار الاشتراكات (تقرير 211): الصفحة وإضافتها وحذفها
 * للأدمن وحده بالدور — كانت الصلاحيتان للأدمن فعليًا من البداية.
 */
test('subscription prices are admin-only after deleting both price permissions', function () {
    expect(\Spatie\Permission\Models\Permission::whereIn('name', ['view subscription prices', 'manage subscription prices'])->count())->toBe(0);

    $admin = adminOnlyUser('admin');
    $this->actingAs($admin)->get(route('subscription-prices.index'))->assertOk();
    $this->actingAs($admin)->post(route('subscription-prices.store'), [
        'circle_level' => 'build', 'education_stage' => 'ابتدائي', 'amount' => 150,
    ])->assertRedirect(route('subscription-prices.index'));
    expect(\App\Models\SubscriptionPrice::count())->toBe(1);

    $gm = adminOnlyUser('general_manager');
    $this->actingAs($gm)->get(route('subscription-prices.index'))->assertStatus(302);
    $this->actingAs($gm)->post(route('subscription-prices.store'), [
        'circle_level' => 'mastery', 'education_stage' => 'ثانوي', 'amount' => 300,
    ])->assertStatus(302);
    expect(\App\Models\SubscriptionPrice::count())->toBe(1);
});

/**
 * صلاحية مستقلة لصفحة الطلاب المتعثرين (تقرير 212): view late and unpaid
 * ممنوحة مبدئيًا للأدوار الخمسة التي كانت ترى الصفحة عبر view subscriptions،
 * وسحبها من دور يقفل الصفحة عليه وحده وبزرّها يختفي من صفحة الاشتراكات.
 */
test('late and unpaid page has its own permission', function () {
    $perm = \Spatie\Permission\Models\Permission::where('name', 'view late and unpaid')->first();
    expect($perm)->not->toBeNull();
    foreach (['admin', 'general_manager', 'manager', 'supervisor', 'teacher'] as $role) {
        expect(\Spatie\Permission\Models\Role::findByName($role)->hasPermissionTo($perm))->toBeTrue($role);
    }

    $supervisor = adminOnlyUser('supervisor');
    $this->actingAs($supervisor)->get(route('subscriptions.late_and_unpaid'))->assertOk();
    $this->actingAs($supervisor)->get(route('subscriptions.index'))
        ->assertOk()->assertSee(route('subscriptions.late_and_unpaid'), false);

    // سحبها من المعلم: الصفحة تُقفل عليه ويختفي زرها، وبقية الاشتراكات كما هي
    \Spatie\Permission\Models\Role::findByName('teacher')->revokePermissionTo($perm);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $teacher = adminOnlyUser('teacher');
    $this->actingAs($teacher)->get(route('subscriptions.late_and_unpaid'))->assertStatus(302);
    $this->actingAs($teacher)->get(route('subscriptions.index'))
        ->assertOk()->assertDontSee(route('subscriptions.late_and_unpaid'), false);
});

/**
 * استرجاع view subscriptions (تقرير 224): حُذفت في 213 لصالح بوابة أدوار،
 * ثم استُرجعت بأمر صاحب الموقع بنفس الحاملين الخمسة — السياسة والمسارات
 * وتمييز الطاقم/ولي أمر داخل المتحكم عادت تعمل بالصلاحية، والوصول ثابت.
 */
test('subscriptions section is permission gated after view subscriptions restore', function () {
    expect(\Spatie\Permission\Models\Permission::where('name', 'view subscriptions')->exists())->toBeTrue();

    foreach (['admin', 'general_manager', 'manager', 'supervisor', 'teacher'] as $role) {
        $this->actingAs(adminOnlyUser($role))->get(route('subscriptions.index'))->assertOk($role);
    }
    $this->actingAs(adminOnlyUser('guardian'))->get(route('subscriptions.index'))->assertStatus(302);
    $this->actingAs(adminOnlyUser('examiner'))->get(route('subscriptions.index'))->assertStatus(302);
});
