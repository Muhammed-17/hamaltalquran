<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CollectionRoundController;

Route::middleware(['auth','verified','role:admin|manager|supervisor'])->group(function () {

    // ─── قائمة التحصيلات ───
    Route::get('/collection-rounds', [CollectionRoundController::class, 'index'])
        ->name('collection-rounds.index');

    // ─── بيانات الجدول التفصيلي (AJAX) ───
    Route::get('/collection-rounds/breakdown', [CollectionRoundController::class, 'getBreakdown'])
        ->name('collection-rounds.breakdown');

    // ─── الحلقات المتاحة (بدون تحصيل معلّق) لشهر معيّن (AJAX) ───
    Route::get('/collection-rounds/available-circles', [CollectionRoundController::class, 'getAvailableCircles'])
        ->name('collection-rounds.available-circles');

    Route::get('/collection-rounds/available-circles-for-user', [CollectionRoundController::class, 'getAvailableCirclesForUser'])
        ->name('collection-rounds.available-circles-for-user');

    // ─── خيارات الفلاتر (AJAX) ───
    Route::get('/collection-rounds/filter-options', [CollectionRoundController::class, 'getFilterOptions'])
        ->name('collection-rounds.filter-options');

    // ─── إنشاء تحصيل جديدة ───
    Route::middleware('permission:create collection rounds')->group(function () {
        Route::get('/collection-rounds/create', [CollectionRoundController::class, 'create'])
            ->name('collection-rounds.create');
        Route::post('/collection-rounds', [CollectionRoundController::class, 'store'])
            ->name('collection-rounds.store');
    });

    // ─── تعديل تحصيل معلّق ───
    Route::middleware('permission:edit collection rounds')->group(function () {
        Route::get('/collection-rounds/{collectionRound}/edit', [CollectionRoundController::class, 'editRound'])
            ->name('collection-rounds.edit');
        Route::put('/collection-rounds/{collectionRound}', [CollectionRoundController::class, 'updateRound'])
            ->name('collection-rounds.update');
    });

    // ─── حذف التحصيل ───
    Route::middleware('permission:delete collection rounds')->group(function () {
        Route::delete('/collection-rounds/{collectionRound}', [CollectionRoundController::class, 'destroyRound'])
            ->name('collection-rounds.destroy');
    });

    // ─── تأكيد / ملاحظة مراجعة تحصيل محددة ───
    Route::middleware('permission:confirm collection rounds')->group(function () {
        Route::get('/collection-rounds/{collectionRound}/confirm', [CollectionRoundController::class, 'showConfirm'])
            ->name('collection-rounds.confirm.show');
        Route::patch('/collection-rounds/{collectionRound}/confirm', [CollectionRoundController::class, 'confirmRound'])
            ->name('collection-rounds.confirm.update');
        Route::patch('/collection-rounds/{collectionRound}/manager-note', [CollectionRoundController::class, 'addManagerNote'])
            ->name('collection-rounds.manager-note');
    });
});