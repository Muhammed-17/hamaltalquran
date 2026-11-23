<?php

/**
 * صفحة «مراجعة مستثناة» بعد إنشاء جدول الأعمار في config/education_stages.php:
 * التقييم يعمل بجدول حقيقي فلا يظهر إلا الشواذ الفعليون.
 * أمر المزامنة students:sync-education-stage باقٍ في النظام بقرار صريح من المالك.
 */

use App\Models\Center;
use App\Models\Student;
use App\Models\User;
use App\Services\EducationStageExclusionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

function reviewStudent(Center $center, string $name, ?string $dob, string $stage, string $grade): Student
{
    return Student::create([
        'name' => $name, 'gender' => 'ذكر', 'center_id' => $center->id, 'status' => 'مقيد',
        'date_of_birth' => $dob, 'educational_stage' => $stage, 'school_grade' => $grade,
    ]);
}

test('review evaluation with the real age table: only genuine anomalies get a reason', function () {
    $center = Center::create(['name' => 'مركز']);
    $svc = new EducationStageExclusionService;
    $reason = fn (Student $s) => $svc->evaluate($s)['reason'];
    $age = fn (int $years) => now()->subYears($years)->format('Y-m-d');

    expect($reason(reviewStudent($center, 'طبيعي', $age(10), 'ابتدائي', 'الخامس')))->toBeNull()
        ->and($reason(reviewStudent($center, 'راسب سنة', $age(11), 'ابتدائي', 'الخامس')))->toBeNull()
        ->and($reason(reviewStudent($center, 'متأخر', $age(13), 'ابتدائي', 'الخامس')))->toContain('متأخر')
        ->and($reason(reviewStudent($center, 'خريج', $age(22), 'خريج', 'لا يوجد')))->toBe('مرحلة محمية بالكامل (خريج)')
        ->and($reason(reviewStudent($center, 'دراسات', $age(24), 'جامعي', 'دراسات عليا')))->toBe('صف مستثنى (دراسات عليا)')
        ->and($reason(reviewStudent($center, 'غريب', $age(6), 'تمهيدي', 'الأول')))->toContain('غير معروفة')
        ->and($reason(reviewStudent($center, 'صغير', $age(3), 'تمهيدي', 'لا يوجد')))->toBeNull()
        ->and($reason(reviewStudent($center, 'بلا ميلاد', null, 'ابتدائي', 'الأول')))->toBeNull();
});

test('the sync command is kept in the system', function () {
    expect(file_exists(app_path('Console/Commands/SyncStudentsEducationStage.php')))->toBeTrue()
        ->and(array_key_exists('students:sync-education-stage', Artisan::all()))->toBeTrue();
});

test('excluded review page lists only the anomalies', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $admin = User::create(['name' => 'admin', 'email' => 'admin@review.local', 'password' => Hash::make('Test123!'), 'status' => 'active']);
    $admin->assignRole('admin');
    $center = Center::create(['name' => 'مركز']);
    reviewStudent($center, 'طالب طبيعي', now()->subYears(10)->format('Y-m-d'), 'ابتدائي', 'الخامس');
    reviewStudent($center, 'طالب متأخر', now()->subYears(13)->format('Y-m-d'), 'ابتدائي', 'الخامس');

    $this->actingAs($admin)->get(route('students.excluded-review'))
        ->assertOk()
        ->assertSee('طالب متأخر')
        ->assertDontSee('طالب طبيعي');
});
