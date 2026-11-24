<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CircleController;

Route::middleware(['auth', 'verified', 'role:admin|general_manager|manager|supervisor|teacher'])->group(function () {

    Route::get('/circles', [CircleController::class, 'index'])->name('circles.index');

    Route::middleware('role:admin')->group(function () {
        Route::get('/circles/create', [CircleController::class, 'create'])->name('circles.create');
        Route::post('/circles', [CircleController::class, 'store'])->name('circles.store');
    });

    Route::get('/circles/{circle}/group-plan', [CircleController::class, 'groupPlan'])->name('circles.group-plan');

    Route::middleware('permission:edit circles')->group(function () {
        Route::get('/circles/{circle}/edit', [CircleController::class, 'edit'])->name('circles.edit');
        Route::match(['put', 'patch'], '/circles/{circle}', [CircleController::class, 'update'])->name('circles.update');
    });

    Route::middleware('role:admin')->group(function () {
        Route::delete('/circles/{circle}', [CircleController::class, 'destroy'])->name('circles.destroy');
    });

    Route::get('/circles/{circle}', [CircleController::class, 'show'])->name('circles.show');
});