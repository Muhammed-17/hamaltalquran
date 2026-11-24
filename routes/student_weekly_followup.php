<?php

namespace App\Routes;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentWeeklyFollowupController;
use App\Models\StudentWeeklyFollowup;

Route::middleware(['auth', 'verified', 'role:admin|general_manager|manager|supervisor|teacher'])->group(function () {

    // Explicit model binding for individual plans
    Route::model('studentWeeklyFollowup', StudentWeeklyFollowup::class);

    // =============================================================
    // Index
    // =============================================================
    Route::get('student-weekly-followups', [StudentWeeklyFollowupController::class, 'indexGroup'])
        ->name('student-weekly-followups.index-group');

    Route::get('student-weekly-followups-individual', [StudentWeeklyFollowupController::class, 'indexIndividual'])
        ->name('student-weekly-followups.index-individual');

    // =============================================================
    // Group Plans (batch-based)
    // =============================================================
    Route::prefix('student-weekly-followups/group')->group(function () {

        Route::middleware('permission:create student weekly followups')->group(function () {
            Route::get('/create', [StudentWeeklyFollowupController::class, 'createGroup'])
                ->name('student-weekly-followups.create-group');
            Route::post('/store', [StudentWeeklyFollowupController::class, 'storeGroup'])
                ->name('student-weekly-followups.store-group');
        });

        Route::get('/circles/{circle}/students', [StudentWeeklyFollowupController::class, 'studentsForCircle'])
            ->name('student-weekly-followups.students-for-circle');
        Route::get('/{batchId}', [StudentWeeklyFollowupController::class, 'showGroup'])
            ->name('student-weekly-followups.show-group');

        Route::middleware('permission:edit student weekly followups')->group(function () {
            Route::get('/{batchId}/edit', [StudentWeeklyFollowupController::class, 'editGroup'])
                ->name('student-weekly-followups.edit-group');
            Route::put('/{batchId}/update', [StudentWeeklyFollowupController::class, 'updateGroup'])
                ->name('student-weekly-followups.update-group');
        });

        Route::middleware('permission:delete student weekly followups')->group(function () {
            Route::delete('/{batchId}', [StudentWeeklyFollowupController::class, 'destroyGroup'])
                ->name('student-weekly-followups.destroy-group');
        });
    });

    // =============================================================
    // Individual Plans (model-based)
    // =============================================================
    Route::prefix('student-weekly-followups/individual')->group(function () {

        Route::middleware('permission:create student weekly followups')->group(function () {
            Route::get('/create', [StudentWeeklyFollowupController::class, 'createIndividual'])
                ->name('student-weekly-followups.create-individual');
            Route::post('/store', [StudentWeeklyFollowupController::class, 'storeIndividual'])
                ->name('student-weekly-followups.store-individual');
        });

        Route::get('/student-weekly-followups/{studentWeeklyFollowup}/show-individual', [StudentWeeklyFollowupController::class, 'showIndividual'])
            ->name('student-weekly-followups.show-individual');
    });

    Route::middleware('permission:edit student weekly followups')->group(function () {
        Route::get('/student-weekly-followups/{studentWeeklyFollowup}/edit-individual', [StudentWeeklyFollowupController::class, 'editIndividual'])
            ->name('student-weekly-followups.edit-individual');
        Route::put('/student-weekly-followups/{studentWeeklyFollowup}/update-individual', [StudentWeeklyFollowupController::class, 'updateIndividual'])
            ->name('student-weekly-followups.update-individual');
    });

    // =============================================================
    // Shared Routes
    // =============================================================
    Route::get('/student-weekly-followups/{studentWeeklyFollowup}', [StudentWeeklyFollowupController::class, 'show'])
        ->name('student-weekly-followups.show');

    Route::middleware('permission:edit student weekly followups')->group(function () {
        Route::get('/student-weekly-followups/{studentWeeklyFollowup}/edit', [StudentWeeklyFollowupController::class, 'edit'])
            ->name('student-weekly-followups.edit');
        Route::put('/student-weekly-followups/{studentWeeklyFollowup}', [StudentWeeklyFollowupController::class, 'update'])
            ->name('student-weekly-followups.update');
    });

    Route::middleware('permission:delete student weekly followups')->group(function () {
        Route::delete('/student-weekly-followups/{studentWeeklyFollowup}', [StudentWeeklyFollowupController::class, 'destroy'])
            ->name('student-weekly-followups.destroy');
    });
});
