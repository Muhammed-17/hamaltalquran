<?php

use App\Http\Controllers\Competition\CompetitionAnswerController;
use App\Http\Controllers\Competition\CompetitionExamAnswerController;
use App\Http\Controllers\Competition\CompetitionController;
use App\Http\Controllers\Competition\CompetitionExaminerController;
use App\Http\Controllers\Competition\CompetitionExaminerQuestionController;
use App\Http\Controllers\Competition\CompetitionLevelController;
use App\Http\Controllers\Competition\CompetitionLevelQuestionController;
use App\Http\Controllers\Competition\CompetitionParticipantController;
use App\Http\Controllers\Competition\CompetitionResultController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:view competitions'])->group(function () {

    Route::prefix('competitions')->name('competitions.')->group(function () {

        //! المسابقات الأساسية
        Route::get('/', [CompetitionController::class, 'index'])->name('index');

        Route::middleware('role:admin')->group(function () {
            Route::get('/create', [CompetitionController::class, 'create'])->name('create');
            Route::post('/', [CompetitionController::class, 'store'])->name('store');
        });

        Route::middleware('role:admin')->group(function () {
            Route::get('/{competition}/edit', [CompetitionController::class, 'edit'])->name('edit');
            Route::put('/{competition}', [CompetitionController::class, 'update'])->name('update');
        });

        Route::middleware('role:admin')->group(function () {
            Route::delete('/{competition}', [CompetitionController::class, 'destroy'])->name('destroy');
        });

        Route::middleware('role:admin')->group(function () {
            Route::post('/{competition}/duplicate', [CompetitionController::class, 'duplicate'])->name('duplicate');
        });

        //! المستويات
        Route::get('/{competition}/levels', [CompetitionLevelController::class, 'index'])->name('levels');

        Route::middleware('role:admin')->group(function () {
            Route::get('/{competition}/levels/select', [CompetitionLevelController::class, 'edit'])->name('levels.select');
            Route::put('/{competition}/levels', [CompetitionLevelController::class, 'update'])->name('levels.update');
            Route::delete('/{competition}/levels/{competitionLevel}', [CompetitionLevelController::class, 'destroy'])->name('levels.destroy');
        });

        //! أسئلة المستوى (مستقلة عن المختبر — إنشاء/تعديل/حذف)
        Route::middleware('permission:view level questions')->group(function () {
            Route::get('/{competition}/level-questions', [CompetitionLevelQuestionController::class, 'index'])->name('level-questions');
            // whereNumber: بدونها يبتلع مسار العرض كلمة create كأنها معرف سؤال فيُرجع 404
            Route::get('/{competition}/level-questions/{question}', [CompetitionLevelQuestionController::class, 'show'])->name('level-questions.show')->whereNumber('question');
        });

        Route::middleware('permission:create level questions')->group(function () {
            Route::get('/{competition}/level-questions/create', [CompetitionLevelQuestionController::class, 'create'])->name('level-questions.create');
            Route::post('/{competition}/level-questions', [CompetitionLevelQuestionController::class, 'store'])->name('level-questions.store');
        });

        Route::middleware('permission:edit level questions')->group(function () {
            Route::get('/{competition}/level-questions/{question}/edit', [CompetitionLevelQuestionController::class, 'edit'])->name('level-questions.edit')->whereNumber('question');
            Route::put('/{competition}/level-questions/{question}', [CompetitionLevelQuestionController::class, 'update'])->name('level-questions.update')->whereNumber('question');
        });

        Route::middleware('permission:delete level questions')->group(function () {
            Route::delete('/{competition}/level-questions/{question}', [CompetitionLevelQuestionController::class, 'destroy'])->name('level-questions.destroy')->whereNumber('question');
        });

        //! المختبرون
        Route::middleware('permission:view competition examiners')->group(function () {
            Route::get('/{competition}/examiners', [CompetitionExaminerController::class, 'index'])->name('examiners');
        });

        Route::middleware('permission:select competition examiners')->group(function () {
            Route::get('/{competition}/examiners/create', [CompetitionExaminerController::class, 'create'])->name('examiners.create');
            Route::post('/{competition}/examiners', [CompetitionExaminerController::class, 'store'])->name('examiners.store');
        });

        Route::middleware('permission:edit examiner levels')->group(function () {
            Route::get('/{competition}/examiners/{competitionExaminer}/edit', [CompetitionExaminerController::class, 'edit'])->name('examiners.edit');
            Route::put('/{competition}/examiners/{competitionExaminer}', [CompetitionExaminerController::class, 'update'])->name('examiners.update');
        });

        Route::middleware('permission:delete competition examiners')->group(function () {
            Route::delete('/{competition}/examiners/{competitionExaminer}', [CompetitionExaminerController::class, 'destroy'])->name('examiners.destroy');
        });

        //! اختيار الأسئلة الخاصة بمختبر معين
        Route::middleware('permission:select examiner questions')->group(function () {
            Route::get('/{competition}/examiners/{competitionExaminer}/questions', [CompetitionExaminerQuestionController::class, 'index'])->name('questions');
            Route::post('/{competition}/examiners/{competitionExaminer}/questions/{question}/toggle-claim', [CompetitionExaminerQuestionController::class, 'toggleClaim'])->name('questions.toggle-claim');
        });

        //! المشاركون
        Route::middleware('permission:view competition participants')->group(function () {
            Route::get('/{competition}/participants', [CompetitionParticipantController::class, 'index'])->name('participants');
        });

        Route::middleware('permission:export competition participants')->group(function () {
            Route::get('/{competition}/participants/export', [CompetitionParticipantController::class, 'exportExcel'])->name('participants.export');
        });

        Route::middleware('permission:create competition participants')->group(function () {
            Route::get('/participants/search-students', [CompetitionParticipantController::class, 'searchStudents'])->name('participants.search-students');
            Route::get('/participants/search-external', [CompetitionParticipantController::class, 'searchExternalParticipants'])->name('participants.search-external');
            Route::get('/{competition}/participants/create', [CompetitionParticipantController::class, 'create'])->name('participants.create');
            Route::post('/{competition}/participants', [CompetitionParticipantController::class, 'store'])->name('participants.store');
        });

        Route::middleware('permission:edit competition participants')->group(function () {
            Route::get('/{competition}/participants/{participant}/edit', [CompetitionParticipantController::class, 'edit'])->name('participants.edit');
            Route::put('/{competition}/participants/{participant}', [CompetitionParticipantController::class, 'update'])->name('participants.update');
        });

        Route::middleware('permission:delete competition participants')->group(function () {
            Route::delete('/{competition}/participants/{participant}', [CompetitionParticipantController::class, 'destroy'])->name('participants.destroy');
        });

        //! النتائج والتقارير
        Route::middleware('role:admin')->group(function () {
            Route::get('/{competition}/results', [CompetitionResultController::class, 'index'])->name('results');
            Route::get('/{competition}/results/export', [CompetitionResultController::class, 'exportExcel'])->name('results.export');
        });

        //! تصحيح واختبار الأدمن
        Route::middleware('permission:examine competition participants')->group(function () {
            Route::get('/overview', [CompetitionAnswerController::class, 'competitions'])->name('overview.index');
            Route::get('/{competition}/overview/levels', [CompetitionAnswerController::class, 'levels'])->name('overview.levels');
            Route::get('/levels/{competitionLevel}/participants', [CompetitionAnswerController::class, 'participants'])->name('level-participants.index');
            Route::get('/participants/{participant}/result', [CompetitionAnswerController::class, 'result'])->name('participants.result');
            Route::prefix('exam')->name('exam.')->group(function () {
                Route::get('/{participant}', [CompetitionExamAnswerController::class, 'show'])->name('show');
                Route::post('/{participant}', [CompetitionExamAnswerController::class, 'store'])->name('store');
                Route::get('/{participant}/review', [CompetitionExamAnswerController::class, 'review'])->name('review');
                Route::post('/{participant}/finalize', [CompetitionExamAnswerController::class, 'finalize'])->name('finalize');
            });
        });
        
        Route::middleware('role:admin')->group(function () {
            Route::get('/participants/{participant}/manual-result', [CompetitionAnswerController::class, 'manualResultForm'])->name('participants.manual-result.form');
            Route::post('/participants/{participant}/manual-result', [CompetitionAnswerController::class, 'storeManualResult'])->name('participants.manual-result.store');
        });
    });
});
