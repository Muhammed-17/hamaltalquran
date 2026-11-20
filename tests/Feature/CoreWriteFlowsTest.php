<?php

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/*
|--------------------------------------------------------------------------
| اختبارات تدفقات الكتابة الأساسية
|--------------------------------------------------------------------------
| تغطي عمليات الإنشاء/التعديل الأهم في النظام كمدير:
| إضافة طالب جديد، تسجيل حضور حلقة، وتعديل اشتراك (تحويله إلى مدفوع).
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');

    $this->center = Center::create(['name' => 'مركز الاختبار']);
    $this->branch = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع الاختبار']);
    $this->circle = Circle::create([
        'branch_id' => $this->branch->id,
        'name'      => 'حلقة الاختبار',
        'type'      => 'بنين',
        'level'     => 'مبتدئ',
    ]);

    $supervisorUser = User::factory()->create(['status' => 'active']);
    $this->supervisor = Teacher::create(['user_id' => $supervisorUser->id]);
});

test('admin can add a new student', function () {
    $response = $this->actingAs($this->admin)->post(route('students.store'), [
        // بيانات أساسية
        'supervisor_id'     => $this->supervisor->id,
        'join_date'         => now()->format('Y-m-d'),
        'applicant'         => 'الأب',
        'name'              => 'طالب اختبار جديد',
        'gender'            => 'ذكر',
        'address'           => 'عنوان اختباري',
        'center_id'         => $this->center->id,
        'guardian_id'       => 'none',
        // الدراسة
        'educational_stage' => 'المرحلة الابتدائية',
        'education_type'    => 'حكومي',
        'school_grade'      => 'الصف الرابع',
        'previous_school'   => 'مدرسة سابقة',
        // الصحة والسمات
        'health_status'         => 'سليم',
        'learning_difficulties' => 'لا يوجد',
        'personal_traits'       => 'هادئ',
        'student_exit_status'   => 'منتظم',
        // مستوى الدخول: إبداع (أبسط مسار تحقق)
        'reading'                      => 'جيدة',
        'center_entry_level'           => 'creativity',
        'preferred_time_creativity'    => 'مسائي',
        'previous_licenses_and_chains' => 'إجازة سابقة',
        'desired_narration_and_path'   => 'حفص عن عاصم',
        // القرار
        'status'            => 'مقيد',
        'decision'          => 'قبول',
        'subscription_fees' => 'مدفوع',
        'received_tools'    => 'تم الاستلام',
    ]);

    $response->assertRedirect(route('students.index'));
    $this->assertDatabaseHas('students', [
        'name'   => 'طالب اختبار جديد',
        'status' => 'مقيد',
    ]);
});

test('admin can record attendance for a circle', function () {
    $student = Student::create([
        'name'      => 'طالب حضور',
        'gender'    => 'ذكر',
        'status'    => 'مقيد',
        'circle_id' => $this->circle->id,
    ]);

    $today = now()->format('Y-m-d');

    $response = $this->actingAs($this->admin)->post(route('attendance.store'), [
        'date'       => $today,
        'circle_id'  => $this->circle->id,
        'attendance' => [
            ['student_id' => $student->id, 'status' => 'present'],
        ],
    ]);

    $response->assertRedirect(route('attendance.index'));
    $this->assertDatabaseHas('attendances', [
        'student_id' => $student->id,
        'date'       => $today,
        'status'     => 'present',
    ]);
});

test('admin can update a subscription to paid', function () {
    $student = Student::create([
        'name'      => 'طالب اشتراك',
        'gender'    => 'أنثى',
        'status'    => 'مقيد',
        'circle_id' => $this->circle->id,
    ]);

    $subscription = Subscription::create([
        'student_id' => $student->id,
        'circle_id'  => $this->circle->id,
        'amount'     => 100,
        'month'      => now()->startOfMonth()->format('Y-m-d'),
        'status'     => 'غير مدفوع',
    ]);

    $response = $this->actingAs($this->admin)->put(route('subscriptions.update', $subscription), [
        'student_id'     => $student->id,
        'circle_id'      => $this->circle->id,
        'teacher_id'     => $this->admin->id,
        'amount'         => 150,
        'month'          => now()->format('Y-m'),
        'status'         => 'مدفوع',
        'payment_method' => 'نقدي',
        'collected_by'   => $this->admin->id,
    ]);

    $response->assertRedirect(route('subscriptions.index'));

    $subscription->refresh();
    expect($subscription->status)->toBe('مدفوع')
        ->and((float) $subscription->amount)->toBe(150.0)
        ->and($subscription->paid_at)->not->toBeNull();
});
