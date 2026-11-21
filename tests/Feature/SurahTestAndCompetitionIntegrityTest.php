<?php

use App\Models\Branch;
use App\Models\Center;
use App\Models\Circle;
use App\Models\Competition;
use App\Models\CompetitionAnswer;
use App\Models\CompetitionExaminer;
use App\Models\CompetitionExaminerLevel;
use App\Models\CompetitionLevel;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionResult;
use App\Models\Examiner;
use App\Models\Level;
use App\Models\Student;
use App\Models\SurahTest;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SurahSeeder;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| سلامة اختبارات السور وتصحيح المسابقات (نوفمبر 2026)
|--------------------------------------------------------------------------
| - لم تكن توجد CompetitionParticipantPolicy أصلًا، فكانت صلاحية
|   examine/review على المشارك تفشل للجميع: بوابة المختبر للتصحيح
|   ونتيجة الأدمن اليدوية مغلقتان بالكامل. أُنشئت الـ policy وسُجلت.
| - حفظ إجابة الاختبار كان يقبل سؤالًا من مستوى آخر غير مستوى المشارك
|   (يُحتسب في نتيجته عند الاعتماد) — الآن يُرفض في الكنترولرين.
| - المختبر كان يستطيع تقييم أسئلة مطالب بها مختبر آخر — الآن مقصور
|   على أسئلته المطالَب بها.
| - إنشاء/تعديل اختبارات السور: الحلقة ضمن نطاق الوصول، والنتائج
|   لطلاب حلقة الاختبار فقط (الصلاحيات عند المدير والمشرف لا المعلم).
| - صفحة إنشاء سؤال مستوى كانت 404: مسار العرض {question} يبتلع كلمة
|   create (قيد whereNumber الآن)، وفوقه 3 أسماء صلاحيات بصيغة مفردة
|   غير موجودة في authorize الكنترولر (create/view level question).
*/
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SurahSeeder::class);
    $this->surah = \App\Models\Surah::first();

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');

    $this->center = Center::create(['name' => 'المركز الأول']);
    $this->branch = Branch::create(['center_id' => $this->center->id, 'name' => 'فرع أول']);
    $this->circle = Circle::create(['branch_id' => $this->branch->id, 'name' => 'حلقة أولى', 'type' => 'group', 'level' => 'مبتدئ']);
    $center2 = Center::create(['name' => 'المركز الثاني']);
    $branch2 = Branch::create(['center_id' => $center2->id, 'name' => 'فرع ثانٍ']);
    $this->foreignCircle = Circle::create(['branch_id' => $branch2->id, 'name' => 'حلقة غريبة', 'type' => 'group', 'level' => 'مبتدئ']);

    $this->teacherUser = User::factory()->create(['status' => 'active']);
    $this->teacherUser->assignRole('teacher');
    $this->teacher = Teacher::create(['user_id' => $this->teacherUser->id, 'branch_id' => $this->branch->id]);
    DB::table('circle_teacher')->insert(['circle_id' => $this->circle->id, 'teacher_id' => $this->teacher->id, 'role' => 'main']);

    $mkStudent = fn (Circle $c, Center $ce, string $name) => Student::create([
        'name' => $name, 'gender' => 'ذكر', 'status' => 'مقيد', 'circle_id' => $c->id, 'center_id' => $ce->id,
    ]);
    $this->student = $mkStudent($this->circle, $this->center, 'طالب حلقتي');
    $this->foreignStudent = $mkStudent($this->foreignCircle, $center2, 'طالب غريب');

    // ===== تجهيز المسابقة =====
    $this->competition = Competition::create(['name' => 'مسابقة الحفظ', 'status' => 'active']);
    $levelA = Level::create(['name' => 'المستوى الأول']);
    $levelB = Level::create(['name' => 'المستوى الثاني']);
    $this->compLevelA = CompetitionLevel::create(['competition_id' => $this->competition->id, 'level_id' => $levelA->id]);
    $this->compLevelB = CompetitionLevel::create(['competition_id' => $this->competition->id, 'level_id' => $levelB->id]);

    // مختبِر أول مسند للمستوى A
    $this->examinerUser = User::factory()->create(['status' => 'active']);
    $this->examinerUser->assignRole('examiner');
    $examiner = Examiner::create(['user_id' => $this->examinerUser->id]);
    $this->compExaminer = CompetitionExaminer::create(['competition_id' => $this->competition->id, 'examiner_id' => $examiner->id]);
    CompetitionExaminerLevel::create(['competition_examiner_id' => $this->compExaminer->id, 'competition_level_id' => $this->compLevelA->id]);

    // مختبِر ثانٍ في نفس المستوى
    $examinerUser2 = User::factory()->create(['status' => 'active']);
    $examinerUser2->assignRole('examiner');
    $examiner2 = Examiner::create(['user_id' => $examinerUser2->id]);
    $this->compExaminer2 = CompetitionExaminer::create(['competition_id' => $this->competition->id, 'examiner_id' => $examiner2->id]);
    CompetitionExaminerLevel::create(['competition_examiner_id' => $this->compExaminer2->id, 'competition_level_id' => $this->compLevelA->id]);

    // أسئلة: سؤال للمستوى A مطالب به الأول، وسؤال للمستوى A مطالب به الثاني، وسؤال للمستوى B
    $this->questionA = CompetitionQuestion::create(['competition_level_id' => $this->compLevelA->id, 'name' => 'سؤال A1', 'type' => 'memorization', 'score' => 10]);
    DB::table('competition_question_examiner')->insert(['competition_question_id' => $this->questionA->id, 'competition_examiner_id' => $this->compExaminer->id]);
    $this->questionA2 = CompetitionQuestion::create(['competition_level_id' => $this->compLevelA->id, 'name' => 'سؤال A2', 'type' => 'memorization', 'score' => 10]);
    DB::table('competition_question_examiner')->insert(['competition_question_id' => $this->questionA2->id, 'competition_examiner_id' => $this->compExaminer2->id]);
    $this->questionB = CompetitionQuestion::create(['competition_level_id' => $this->compLevelB->id, 'name' => 'سؤال B1', 'type' => 'memorization', 'score' => 10]);
    DB::table('competition_question_examiner')->insert(['competition_question_id' => $this->questionB->id, 'competition_examiner_id' => $this->compExaminer->id]);

    $this->participant = CompetitionParticipant::create([
        'competition_id' => $this->competition->id,
        'competition_level_id' => $this->compLevelA->id,
        'center_id' => $this->center->id,
        'student_id' => $this->student->id,
    ]);
});

test('supervisor cannot create a group surah test for a foreign circle or with foreign students', function () {
    // مشرف على الفرع الأول (صلاحيات اختبارات السور عند المدير والمشرف لا المعلم)
    $supUser = User::factory()->create(['status' => 'active']);
    $supUser->assignRole('supervisor');
    $supTeacher = Teacher::create(['user_id' => $supUser->id, 'branch_id' => $this->branch->id]);
    $this->branch->supervisors()->syncWithoutDetaching([$supTeacher->id]);

    $payload = [
        'test_type' => 'group',
        'surah_id' => $this->surah->id,
        'teacher_id' => $this->teacher->id,
        'test_date' => '2026-11-20',
    ];

    // حلقة غريبة وطالب غريب: يُرفض ولا يُحفظ شيء
    $this->actingAs($supUser)->post(route('surah-tests.store'), array_merge($payload, [
        'circle_id' => $this->foreignCircle->id,
        'results' => [[
            'student_id' => $this->foreignStudent->id,
            'prompt_errors' => 0, 'tashkeel_errors' => 0, 'percentage' => 90, 'level' => 'ممتاز',
        ]],
    ]));
    expect(SurahTest::withoutGlobalScopes()->count())->toBe(0);

    // حلقة فرعه لكن بطالب من حلقة أخرى: يُرفض (النتائج لطلاب الحلقة فقط)
    $this->actingAs($supUser)->post(route('surah-tests.store'), array_merge($payload, [
        'circle_id' => $this->circle->id,
        'results' => [[
            'student_id' => $this->foreignStudent->id,
            'prompt_errors' => 0, 'tashkeel_errors' => 0, 'percentage' => 90, 'level' => 'ممتاز',
        ]],
    ]));
    expect(SurahTest::withoutGlobalScopes()->count())->toBe(0);

    // حلقته وطالبه: ينجح
    $this->actingAs($supUser)->post(route('surah-tests.store'), array_merge($payload, [
        'circle_id' => $this->circle->id,
        'results' => [[
            'student_id' => $this->student->id,
            'prompt_errors' => 1, 'tashkeel_errors' => 2, 'percentage' => 85, 'level' => 'جيد جداً',
        ]],
    ]))->assertRedirect();
    expect(SurahTest::withoutGlobalScopes()->count())->toBe(1);
});

test('supervisor cannot move a surah test to a foreign circle via update', function () {
    $supUser = User::factory()->create(['status' => 'active']);
    $supUser->assignRole('supervisor');
    $supTeacher = Teacher::create(['user_id' => $supUser->id, 'branch_id' => $this->branch->id]);
    $this->branch->supervisors()->syncWithoutDetaching([$supTeacher->id]);

    $this->actingAs($supUser)->post(route('surah-tests.store'), [
        'test_type' => 'group',
        'circle_id' => $this->circle->id,
        'surah_id' => $this->surah->id,
        'teacher_id' => $this->teacher->id,
        'test_date' => '2026-11-20',
        'results' => [[
            'student_id' => $this->student->id,
            'prompt_errors' => 1, 'tashkeel_errors' => 2, 'percentage' => 85, 'level' => 'جيد جداً',
        ]],
    ]);
    $test = SurahTest::withoutGlobalScopes()->first();
    $result = $test->results()->first();

    $this->actingAs($supUser)->put(route('surah-tests.update', $test), [
        'circle_id' => $this->foreignCircle->id,
        'surah_id' => $this->surah->id,
        'teacher_id' => $this->teacher->id,
        'test_date' => '2026-11-20',
        'results' => [[
            'id' => $result->id,
            'prompt_errors' => 3, 'tashkeel_errors' => 2, 'percentage' => 80, 'level' => 'جيد',
        ]],
    ]);
    expect($test->fresh()->circle_id)->toBe($this->circle->id);
});

test('assigned examiner can open the exam page for a participant', function () {
    $page = $this->actingAs($this->examinerUser)->get(route('examiner.exam.show', $this->participant));
    $page->assertOk();
});

test('admin exam flow scores and finalizes correctly', function () {
    // سؤال من مستوى المشارك نفسه: 10 - (2×1) - (2×0.5) = 7
    $this->actingAs($this->admin)->post(route('competitions.exam.store', $this->participant), [
        'competition_question_id' => $this->questionA->id,
        'answered' => 1,
        'memorization_mistakes' => 2,
        'tashkeel_mistakes' => 2,
        'action' => 'save',
    ])->assertRedirect();

    $answer = CompetitionAnswer::first();
    expect($answer)->not->toBeNull()
        ->and((float) $answer->score)->toBe(7.0);

    $this->actingAs($this->admin)->post(route('competitions.exam.finalize', $this->participant))
        ->assertRedirect();
    expect((float) CompetitionResult::first()->total_score)->toBe(7.0);
});

test('exam answer for a question from another level is rejected', function () {
    $this->actingAs($this->admin)->post(route('competitions.exam.store', $this->participant), [
        'competition_question_id' => $this->questionB->id,
        'answered' => 1,
        'memorization_mistakes' => 0,
        'tashkeel_mistakes' => 0,
        'action' => 'save',
    ]);
    expect(CompetitionAnswer::count())->toBe(0);
});

test('admin can store a manual result for a participant', function () {
    $this->actingAs($this->admin)->post(route('competitions.participants.manual-result.store', $this->participant), [
        'total_score' => 88.5,
    ])->assertRedirect();

    expect((float) CompetitionResult::first()->total_score)->toBe(88.5);
});

test('level questions create page is reachable and show still works', function () {
    // كان مسار العرض {question} يبتلع كلمة create فيُرجع 404،
    // وفوقه أسماء صلاحيات مفردة خاطئة في authorize الكنترولر
    $level = Level::create(['name' => 'مستوى الأسئلة']);
    $compLevel = CompetitionLevel::create(['competition_id' => $this->competition->id, 'level_id' => $level->id]);
    $question = CompetitionQuestion::create([
        'competition_level_id' => $compLevel->id, 'name' => 'سؤال مستوى', 'type' => 'memorization', 'score' => 10,
    ]);

    $this->actingAs($this->admin)->get(route('competitions.level-questions.create', $this->competition))
        ->assertOk();
    $this->actingAs($this->admin)->get(route('competitions.level-questions.show', [$this->competition, $question]))
        ->assertOk();
});

test('examiner cannot grade a question claimed by another examiner', function () {
    $this->actingAs($this->examinerUser)->post(route('examiner.exam.store', $this->participant), [
        'competition_question_id' => $this->questionA2->id,
        'answered' => 1,
        'memorization_mistakes' => 0,
        'tashkeel_mistakes' => 0,
        'action' => 'save',
    ]);
    expect(CompetitionAnswer::count())->toBe(0);

    // سؤاله هو: ينجح
    $this->actingAs($this->examinerUser)->post(route('examiner.exam.store', $this->participant), [
        'competition_question_id' => $this->questionA->id,
        'answered' => 1,
        'memorization_mistakes' => 1,
        'tashkeel_mistakes' => 0,
        'action' => 'save',
    ]);
    expect(CompetitionAnswer::count())->toBe(1);
});
