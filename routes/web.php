<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuardianSearchController;
use App\Http\Controllers\CronController;

// ================================================================
// Public Routes
// ================================================================
Route::get('/', [WelcomeController::class, 'index'])->name('home');

// ================================================================
// Cron Endpoints (محمية بـ token سري، تُستدعى من cron-job.org)
// ================================================================
Route::get('/cron/{task}', [CronController::class, 'run'])
    ->middleware('throttle:10,1')
    ->name('cron.run');

// ================================================================
// Dashboard Routes
// ================================================================

// ✅ dashboard للأدوار الإدارية — مع permission check
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware([
        'auth',
        'verified',
        'not.guardian',
        'role:admin',
    ])
    ->name('dashboard');

// ================================================================
// Profile Routes
// ================================================================
Route::middleware(['auth', 'permission:edit profile'])->group(function () {
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
});

// ================================================================
// Guardian Search Routes
// ✅ منفصلة عن /api — داخل web.php مع throttle
// ================================================================
Route::middleware(['auth', 'throttle:30,1'])->group(function () {

    // البحث عن ولي أمر
    Route::get('/guardians/search', [GuardianSearchController::class, 'search'])
        ->name('guardians.search');

    // ✅ التحقق من وجود حساب بالإيميل أو الموبايل
    Route::get('/guardians/check', [GuardianSearchController::class, 'check'])
        ->name('guardians.check');
});

// ================================================================
// Sub-route files
// ================================================================
require __DIR__ . '/auth.php';
require __DIR__ . '/student.php';
require __DIR__ . '/circle.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/attendance.php';
require __DIR__ . '/teacher.php';
require __DIR__ . '/subscription.php';
require __DIR__ . '/center.php';
require __DIR__ . '/branch.php';
require __DIR__ . '/guardian_account.php';
require __DIR__ . '/guardian.php';
require __DIR__ . '/collection_round.php';
require __DIR__ . '/group_session_plan.php';
require __DIR__ . '/student_construction_detail.php';
require __DIR__ . '/student_weekly_followup.php';
require __DIR__ . '/behavioral_note.php';
require __DIR__ . '/surah_test.php';
require __DIR__ . '/competition.php';
require __DIR__ . '/external_participant.php';
require __DIR__ . '/examiner.php';
require __DIR__ . '/level.php';
require __DIR__ . '/examiner_account.php';
require __DIR__ . '/tafsir_file.php';
