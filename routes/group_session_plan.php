<?php

namespace App\Routes;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GroupSessionPlanController;

Route::middleware(['auth', 'verified', 'role:admin|general_manager|manager|supervisor|teacher'])->group(function () {

    Route::get('/group-session-plans', [GroupSessionPlanController::class, 'index'])->name('group-session-plans.index');

    Route::middleware('role:admin')->group(function () {
        Route::get('/group-session-plans/create', [GroupSessionPlanController::class, 'create'])->name('group-session-plans.create');
        Route::post('/group-session-plans', [GroupSessionPlanController::class, 'store'])->name('group-session-plans.store');
    });

    Route::get('/group-session-plans/{groupSessionPlan}', [GroupSessionPlanController::class, 'show'])->name('group-session-plans.show');

    Route::middleware('role:admin|general_manager|manager|supervisor')->group(function () {
        Route::get('/group-session-plans/{groupSessionPlan}/edit', [GroupSessionPlanController::class, 'edit'])->name('group-session-plans.edit');
        Route::put('/group-session-plans/{groupSessionPlan}', [GroupSessionPlanController::class, 'update'])->name('group-session-plans.update');
    });

    Route::middleware('role:admin|general_manager|manager|supervisor')->group(function () {
        Route::delete('/group-session-plans/{groupSessionPlan}', [GroupSessionPlanController::class, 'destroy'])->name('group-session-plans.destroy');
    });
});