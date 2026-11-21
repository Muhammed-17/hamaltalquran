<?php

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\EducationalLesson;
use App\Models\Recommendation;
use App\Models\Student;
use App\Models\StudentWeeklyDiscipline;
use App\Models\StudentWeeklyEducationalLesson;
use App\Models\StudentWeeklyFollowup;
use App\Models\StudentWeeklyFoundationLevel;
use App\Models\StudentWeeklyNewMemorization;
use App\Models\StudentWeeklyOldMemorization;
use App\Models\StudentWeeklyRevision;
use App\Models\StudentWeeklyTajweedAssessment;
use App\Models\Teacher;
use App\Models\User;
use App\Services\RecommendationService;
use Database\Seeders\RecommendationTemplateSeeder;
use Database\Seeders\SurahSeeder;

/*
|--------------------------------------------------------------------------
| نظام التوصيات الأسبوعية (نوفمبر 2026)
|--------------------------------------------------------------------------
| - التوليد يبني جملة من قالب كل قسم من الأقسام الثمانية حسب مستواه.
| - إعادة التوليد تُحدّث نفس الصف ولا تُنشئ نسخًا مكررة (قيد فريد على
|   weekly_followup_id) وتحافظ على مراجعة المشرف وتوقيعه.
| - متابعة بلا أي بيانات = النص الاحتياطي.
*/

test('recommendation generation produces full text and preserves supervisor review', function () {
    $this->seed(SurahSeeder::class);
    $this->seed(RecommendationTemplateSeeder::class);

    $center = Center::create(['name' => 'مركز التوصيات']);
    $branch = Branch::create(['center_id' => $center->id, 'name' => 'فرع']);
    $circle = Circle::create(['branch_id' => $branch->id, 'name' => 'حلقة', 'type' => 'بنين', 'level' => 'مبتدئ']);
    $teacherUser = User::factory()->create(['status' => 'active']);
    $teacher = Teacher::create(['user_id' => $teacherUser->id, 'branch_id' => $branch->id]);
    $student = Student::create([
        'name' => 'طالب التوصية', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $circle->id, 'center_id' => $center->id,
    ]);

    $weekStart = now()->startOfWeek();
    $followup = StudentWeeklyFollowup::create([
        'plan_type' => 'individual', 'center_id' => $center->id, 'circle_id' => $circle->id,
        'student_id' => $student->id, 'teacher_id' => $teacher->id,
        'week_start' => $weekStart->format('Y-m-d'), 'week_end' => now()->endOfWeek()->format('Y-m-d'),
        'study_days' => ['السبت', 'الأحد'],
    ]);

    $plan = ['weekly_followup_id' => $followup->id, 'plan_from_surah_id' => 1, 'plan_from_ayah' => 1, 'plan_to_surah_id' => 1, 'plan_to_ayah' => 7];
    StudentWeeklyNewMemorization::create($plan + ['average_level' => 'ممتاز']);
    StudentWeeklyRevision::create($plan + ['average_level' => 'جيد']);
    StudentWeeklyOldMemorization::create($plan + ['average_level' => 'جيد جداً']);
    StudentWeeklyDiscipline::create(['weekly_followup_id' => $followup->id, 'level' => 'ممتاز']);
    StudentWeeklyTajweedAssessment::create(['weekly_followup_id' => $followup->id, 'level' => 'جيد جداً']);
    StudentWeeklyFoundationLevel::create(['weekly_followup_id' => $followup->id, 'level' => 'مقبول']);
    $lesson = EducationalLesson::create(['center_id' => $center->id, 'title' => 'درس', 'created_by' => $teacherUser->id]);
    StudentWeeklyEducationalLesson::create(['weekly_followup_id' => $followup->id, 'educational_lesson_id' => $lesson->id, 'level' => 'جيد']);

    foreach (range(0, 3) as $i) {
        Attendance::create([
            'student_id' => $student->id, 'circle_id' => $circle->id,
            'date' => $weekStart->copy()->addDays($i)->format('Y-m-d'), 'status' => 'present',
        ]);
    }

    $service = app(RecommendationService::class);
    $rec = $service->generateAndStore($followup);
    $text = $rec->generated_recommendation;

    // جمل من كل الأقسام الثمانية
    expect($text)
        ->toContain('تفوقًا ملحوظًا في الحفظ الجديد')
        ->toContain('يحتاج إلى مزيد من التكرار')
        ->toContain('تثبيت مستمر')
        ->toContain('انضباط ممتاز')
        ->toContain('مستوى تجويد جيد جدًا')
        ->toContain('يحتاج إلى مزيد من العمل الأساسي')
        ->toContain('استيعاب جيد للدرس التربوي')
        ->toContain('التزام ممتاز بالحضور')
        ->not->toBe('لا توجد بيانات كافية لتوليد توصية لهذا الأسبوع.');

    // إعادة التوليد: نفس الصف يتحدث، لا نسخ مكررة، ومراجعة المشرف محفوظة
    $rec->update(['supervisor_recommendation' => 'توصية المشرف اليدوية', 'supervisor_id' => $teacher->id, 'signed_at' => now()]);
    $service->generateAndStore($followup->fresh());
    $service->generateAndStore($followup->fresh());

    $rows = Recommendation::where('weekly_followup_id', $followup->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->supervisor_recommendation)->toBe('توصية المشرف اليدوية')
        ->and($rows->first()->signed_at)->not->toBeNull();

    // متابعة فارغة تمامًا = نص احتياطي
    $student2 = Student::create([
        'name' => 'طالب فارغ', 'gender' => 'ذكر', 'status' => 'مقيد',
        'circle_id' => $circle->id, 'center_id' => $center->id,
    ]);
    $empty = StudentWeeklyFollowup::create([
        'plan_type' => 'individual', 'center_id' => $center->id, 'circle_id' => $circle->id,
        'student_id' => $student2->id, 'teacher_id' => $teacher->id,
        'week_start' => $weekStart->format('Y-m-d'), 'week_end' => now()->endOfWeek()->format('Y-m-d'),
        'study_days' => ['السبت'],
    ]);
    $rec2 = $service->generateAndStore($empty);
    expect($rec2->generated_recommendation)->toBe('لا توجد بيانات كافية لتوليد توصية لهذا الأسبوع.');
});
