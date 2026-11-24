<?php

namespace App\Routes;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentConstructionDetailController;

Route::middleware(['auth', 'verified', 'role:admin|general_manager|manager|supervisor|teacher'])->group(function () {

    Route::middleware('role:admin')->group(function () {
        Route::get('/student-construction-details/create', [StudentConstructionDetailController::class, 'create'])
            ->name('student-construction-details.create');
        Route::post('/student-construction-details', [StudentConstructionDetailController::class, 'store'])
            ->name('student-construction-details.store');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/student-construction-details/{studentConstructionDetail}/edit', [StudentConstructionDetailController::class, 'edit'])
            ->name('student-construction-details.edit');
        Route::put('/student-construction-details/{studentConstructionDetail}', [StudentConstructionDetailController::class, 'update'])
            ->name('student-construction-details.update');
    });

    Route::middleware('role:admin')->group(function () {
        Route::delete('/student-construction-details/{studentConstructionDetail}', [StudentConstructionDetailController::class, 'destroy'])
            ->name('student-construction-details.destroy');
    });

    Route::get('/student-construction-details/{studentConstructionDetail}', [StudentConstructionDetailController::class, 'show'])
        ->name('student-construction-details.show');
});
