<?php

use App\Http\Controllers\ExaminerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','verified' ,'role:admin'])->group(function () {
    Route::resource('examiners', ExaminerController::class);
});