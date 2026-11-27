<?php

use App\Http\Controllers\TafsirFileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','verified','role:admin'])->group(function () {
    Route::resource('tafsir-files', TafsirFileController::class)->except(['show']);
});