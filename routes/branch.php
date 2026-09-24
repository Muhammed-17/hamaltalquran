<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BranchController;

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::resource('branches', BranchController::class);
    Route::get('branches/for-center', [BranchController::class, 'forCenter'])->name('branches.for-center');
});
