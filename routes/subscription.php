<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPriceController;
use App\Http\Controllers\SubscriptionTransferController;

Route::middleware(['auth', 'verified', 'role:admin|general_manager|manager|supervisor|teacher'])->group(function () {

    Route::get('subscriptions/filter-options', [SubscriptionController::class, 'getFilterOptions'])
        ->name('subscriptions.filter-options');

    Route::get('subscriptions/students-for-circle', [SubscriptionController::class, 'studentsForCircle'])
        ->name('subscriptions.students-for-circle');

    // ─── عرض الاشتراكات ───────────────────────────────────────────
    Route::middleware('role:admin|general_manager|manager|supervisor|teacher|guardian')->group(function () {
        Route::get('/subscriptions', [SubscriptionController::class, 'index'])
            ->name('subscriptions.index');
    });

        // ─── تحويل الأموال (نقل اشتراكات محصَّلة بين المعلمين) ──────────
    Route::middleware('permission:transfer subscriptions')->prefix('subscription-transfers')->name('subscription-transfers.')->group(function () {
        Route::get('/', [SubscriptionTransferController::class, 'index'])->name('index');
        Route::get('/search', [SubscriptionTransferController::class, 'search'])->name('search');
        Route::post('/transfer', [SubscriptionTransferController::class, 'transfer'])->name('transfer');
        Route::get('/history', [SubscriptionTransferController::class, 'history'])->name('history');
    });
});

// ─── الطلاب المتعثرين (صلاحية مستقلة: view late and unpaid) ───
Route::middleware(['auth', 'verified', 'permission:view late and unpaid'])->group(function () {
    Route::get('/subscriptions/late-and-unpaid', [SubscriptionController::class, 'lateAndUnpaid'])
        ->name('subscriptions.late_and_unpaid');
    Route::get('/subscriptions/late-and-unpaid/{student}/months', [SubscriptionController::class, 'lateDetail'])
        ->name('subscriptions.late_detail');
    Route::get('/subscriptions/student/{student}/details-unpaid', [SubscriptionController::class, 'DetailsUnpaid'])
        ->name('subscriptions.details_unpaid');
});

// ─── إرسال تنبيه اشتراك متأخر (بدون قيد صلاحية — مقصود) ────────
Route::post('/subscriptions/{student}/notify-unpaid', [SubscriptionController::class, 'notifyUnpaid'])
    ->name('subscriptions.notify-unpaid');

// ─── إضافة اشتراك ─────────────────────────────────────────────
Route::middleware('permission:create subscriptions')->group(function () {
    Route::get('/subscriptions/create', [SubscriptionController::class, 'create'])
        ->name('subscriptions.create');
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])
        ->name('subscriptions.store');
});

// ─── تعديل اشتراك ─────────────────────────────────────────────
Route::middleware('permission:edit subscriptions')->group(function () {
    Route::get('/subscriptions/{subscription}/edit', [SubscriptionController::class, 'edit'])
        ->name('subscriptions.edit');
    Route::put('/subscriptions/{subscription}', [SubscriptionController::class, 'update'])
        ->name('subscriptions.update');
});

// ─── حذف اشتراك ───────────────────────────────────────────────
Route::middleware('permission:delete subscriptions')->group(function () {
    Route::delete('/subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])
        ->name('subscriptions.destroy');
});

// ─── أسعار الاشتراكات (أدمن فقط — حُذفت صلاحيتا view/manage subscription prices) ───
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/subscription-prices', [SubscriptionPriceController::class, 'index'])
        ->name('subscription-prices.index');
    Route::post('/subscription-prices', [SubscriptionPriceController::class, 'store'])
        ->name('subscription-prices.store');
    Route::delete('/subscription-prices/{subscriptionPrice}', [SubscriptionPriceController::class, 'destroy'])
        ->name('subscription-prices.destroy');
});
