<?php

use App\Http\Controllers\Admin\WelcomeMediaController;
use Illuminate\Support\Facades\Route;

// محتوى الصفحة الرئيسية (سلايدر/معرض/فيديوهات) — إدارة الأدمن فقط
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/admin/welcome-media', [WelcomeMediaController::class, 'index'])->name('welcome-media.index');
    Route::get('/admin/welcome-media/create', [WelcomeMediaController::class, 'create'])->name('welcome-media.create');
    Route::post('/admin/welcome-media', [WelcomeMediaController::class, 'store'])->name('welcome-media.store');
    Route::get('/admin/welcome-media/{welcome_medium}/edit', [WelcomeMediaController::class, 'edit'])->name('welcome-media.edit');
    Route::put('/admin/welcome-media/{welcome_medium}', [WelcomeMediaController::class, 'update'])->name('welcome-media.update');
    Route::delete('/admin/welcome-media/{welcome_medium}', [WelcomeMediaController::class, 'destroy'])->name('welcome-media.destroy');
});
