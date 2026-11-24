<?php

use App\Http\Controllers\GuardianAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','verified' ,'not.guardian', 'role:admin'])
    ->prefix('guardians')
    ->name('guardians.')
    ->group(function () {
        Route::get('/',                [GuardianAccountController::class, 'index'])->name('index');
        Route::get('/create',          [GuardianAccountController::class, 'create'])->name('create');  // ✅ قبل /{guardian}
        Route::post('/',               [GuardianAccountController::class, 'store'])->name('store');    // ✅ جديد
        Route::get('/{guardian}/edit', [GuardianAccountController::class, 'edit'])->name('edit');
        Route::put('/{guardian}',      [GuardianAccountController::class, 'update'])->name('update');
        Route::patch('/{guardian}/toggle-status', [GuardianAccountController::class, 'toggleStatus'])->name('toggleStatus');
        Route::delete('/{guardian}',   [GuardianAccountController::class, 'destroy'])->name('destroy');
        Route::get('/{guardian}',      [GuardianAccountController::class, 'show'])->name('show');      // ✅ آخراً دائماً
    });
