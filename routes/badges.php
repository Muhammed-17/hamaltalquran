<?php

use App\Http\Controllers\Badges\BadgeCollectionRoundController;
use App\Http\Controllers\Badges\BadgeDistributionController;
use App\Http\Controllers\Badges\BadgeEntitlementController;
use App\Http\Controllers\Badges\BadgeShortageController;
use App\Http\Controllers\Badges\BadgeStockController;
use App\Http\Controllers\Badges\BadgeTeacherHandoverController;
use App\Http\Controllers\Badges\BadgeTypeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مسارات نظام الأوسمة — ملف مستقل مسجل من routes/web.php
| التفويض التفصيلي داخل Policies، والصلاحيات على مستوى المجموعات هنا.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])
    ->prefix('badges')
    ->name('badges.')
    ->group(function () {

        // ─── أنواع الأوسمة ───
        Route::resource('types', BadgeTypeController::class)->middleware('role:admin');

        // ─── المخزون المركزي ───
        Route::get('stock', [BadgeStockController::class, 'index'])
            ->middleware('role:admin')
            ->name('stock.index');
        Route::get('stock/history', [BadgeStockController::class, 'history'])
            ->middleware('role:admin')
            ->name('stock.history');
        Route::post('stock/movements', [BadgeStockController::class, 'storeMovement'])
            ->name('stock.movements.store');
        Route::post('stock/returns', [BadgeStockController::class, 'storeReturn'])
            ->name('stock.returns.store');

        // ─── تسليم الأوسمة للمعلمين ───
        Route::get('handovers/report', [BadgeTeacherHandoverController::class, 'report'])
            ->middleware('role:admin')
            ->name('handovers.report');
        Route::get('handovers/statistics', [BadgeTeacherHandoverController::class, 'statistics'])
            ->middleware('role:admin')
            ->name('handovers.statistics');
        Route::resource('handovers', BadgeTeacherHandoverController::class)
            ->only(['index', 'create', 'store']);
        Route::post('handovers/{handover}/cancel', [BadgeTeacherHandoverController::class, 'cancel'])
            ->name('handovers.cancel');

        // ─── استحقاقات الطلاب ───
        Route::post('entitlements/daily', [BadgeEntitlementController::class, 'storeDaily'])
            ->name('entitlements.daily-store');
        Route::get('entitlements/statistics', [BadgeEntitlementController::class, 'statistics'])
            ->middleware('role:admin')
            ->name('entitlements.statistics');
        Route::get('entitlements/statistics/distributors', [BadgeEntitlementController::class, 'statisticsDistributors'])
            ->middleware('role:admin')
            ->name('entitlements.statistics-distributors');
        Route::get('entitlements/statistics/students', [BadgeEntitlementController::class, 'statisticsStudents'])
            ->middleware('role:admin')
            ->name('entitlements.statistics-students');
        Route::get('entitlements/report', [BadgeEntitlementController::class, 'report'])
            ->middleware('role:admin')
            ->name('entitlements.report');
        Route::resource('entitlements', BadgeEntitlementController::class)
            ->except(['destroy', 'show']);
        Route::post('entitlements/{entitlement}/cancel', [BadgeEntitlementController::class, 'cancel'])
            ->name('entitlements.cancel');

        // ─── توزيع الأوسمة ───
        Route::resource('distributions', BadgeDistributionController::class)
            ->only(['index', 'store', 'show']);
        Route::post('distributions/{distribution}/cancel', [BadgeDistributionController::class, 'cancel'])
            ->name('distributions.cancel');

        // ─── الأوسمة الناقصة ───
        Route::get('shortages/deliver', [BadgeShortageController::class, 'deliver'])
            ->name('shortages.deliver');
        Route::post('shortages/deliver', [BadgeShortageController::class, 'deliverStore'])
            ->name('shortages.deliver-store');
        Route::get('shortages', [BadgeShortageController::class, 'index'])
            ->name('shortages.index');
        Route::get('shortages/{shortage}', [BadgeShortageController::class, 'show'])
            ->name('shortages.show');
        Route::post('shortages/{shortage}/resolve', [BadgeShortageController::class, 'resolve'])
            ->name('shortages.resolve');

        // ─── جولات تحصيل الأوسمة (جلسة: تسوية طالب طالب ثم اعتماد) ───
        Route::post('collections/settle-student', [BadgeCollectionRoundController::class, 'settleStudent'])
            ->name('collections.settle-student');
        Route::resource('collections', BadgeCollectionRoundController::class)
            ->except(['destroy', 'store']);
        Route::put('collections/items/{item}', [BadgeCollectionRoundController::class, 'updateItem'])
            ->name('collections.items.update');
        Route::post('collections/{collection}/complete', [BadgeCollectionRoundController::class, 'complete'])
            ->name('collections.complete');
        Route::post('collections/{collection}/cancel', [BadgeCollectionRoundController::class, 'cancel'])
            ->name('collections.cancel');
    });
