<?php

use App\Http\Controllers\ExternalParticipantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','verified', 'permission:manage external participants'])->group(function () {
    Route::resource('external-participants', ExternalParticipantController::class)
        ->parameters(['external-participants' => 'external_participant']);
});