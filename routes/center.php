<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CenterController;

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/centers', [CenterController::class, 'index'])->name('centers.index');
    Route::post('/centers', [CenterController::class, 'store'])->name('centers.store');
    Route::delete('/centers/{center}', [CenterController::class, 'destroy'])->name('centers.destroy');

    Route::get('/centers/{center}', [CenterController::class, 'show'])->name('centers.show');
    Route::post('/centers/{center}/educational-lessons', [CenterController::class, 'storeEducationalLesson'])->name('centers.educational-lessons.store');

    Route::put('/educational-lessons/{educationalLesson}', [CenterController::class, 'updateEducationalLesson'])->name('educational-lessons.update');
    Route::delete('/educational-lessons/{educationalLesson}', [CenterController::class, 'destroyEducationalLesson'])->name('educational-lessons.destroy');
});