<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BranchController;

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('branches/for-center', [BranchController::class, 'forCenter'])->name('branches.for-center');
    Route::resource('branches', BranchController::class);
});
