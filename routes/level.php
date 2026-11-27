<?php

use App\Http\Controllers\LevelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified','role:admin'])->group(function () {
    Route::resource('levels', LevelController::class)->parameters(['levels' => 'level']);
});