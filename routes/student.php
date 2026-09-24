<?php

use App\Http\Controllers\StudentController;
use App\Http\Controllers\FavoriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:view students'])->group(function () {

    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/export', [StudentController::class, 'export'])->name('students.export');
    Route::get('/students/excluded-review', [StudentController::class, 'excludedReview'])
        ->name('students.excluded-review');

    Route::post('/students/{student}/favorite', [FavoriteController::class, 'store'])
        ->name('students.favorite.store');
    Route::delete('/students/{student}/favorite', [FavoriteController::class, 'destroy'])
        ->name('students.favorite.destroy');
    Route::get('/favorites', [FavoriteController::class, 'index'])
        ->name('favorites.index');

    Route::middleware('permission:create students')->group(function () {
        Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
        Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    });

    Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');

    Route::middleware('permission:edit students')->group(function () {
        Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    });

    Route::middleware('permission:delete students')->group(function () {
        Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    });

    Route::middleware('permission:assign student to circle')->group(function () {
        Route::post('/students/{student}/assign-circle', [StudentController::class, 'assignCircle'])->name('students.assign-circle');
    });
});
