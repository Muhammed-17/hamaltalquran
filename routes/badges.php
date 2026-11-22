<?php

use App\Http\Controllers\Badges\BadgeCollectionRoundController;
use App\Http\Controllers\Badges\BadgeDistributionController;
use App\Http\Controllers\Badges\BadgeEntitlementController;
use App\Http\Controllers\Badges\BadgeLogController;
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
        Route::resource('types', BadgeTypeController::class);

        // ─── المخزون المركزي ───
        Route::get('stock', [BadgeStockController::class, 'index'])
            ->middleware('permission:badges.stock.view')
            ->name('stock.index');
        Route::get('stock/history', [BadgeStockController::class, 'history'])
            ->middleware('permission:badges.stock.history')
            ->name('stock.history');
        Route::post('stock/movements', [BadgeStockController::class, 'storeMovement'])
            ->name('stock.movements.store');
        Route::post('stock/returns', [BadgeStockController::class, 'storeReturn'])
            ->name('stock.returns.store');

        // ─── تسليم الأوسمة للمعلمين ───
        Route::get('handovers/report', [BadgeTeacherHandoverController::class, 'report'])
            ->middleware('permission:badges.reports.view')
            ->name('handovers.report');
        Route::get('handovers/statistics', [BadgeTeacherHandoverController::class, 'statistics'])
            ->middleware('permission:badges.handovers.view')
            ->name('handovers.statistics');
        Route::resource('handovers', BadgeTeacherHandoverController::class)
            ->only(['index', 'create', 'store', 'show']);
        Route::post('handovers/{handover}/cancel', [BadgeTeacherHandoverController::class, 'cancel'])
            ->name('handovers.cancel');

        // ─── استحقاقات الطلاب ───
        Route::get('entitlements/circle-data', [BadgeEntitlementController::class, 'circleData'])
            ->name('entitlements.circle-data');
        Route::get('entitlements/statistics', [BadgeEntitlementController::class, 'statistics'])
            ->middleware('permission:badges.entitlements.view')
            ->name('entitlements.statistics');
        Route::get('entitlements/report', [BadgeEntitlementController::class, 'report'])
            ->middleware('permission:badges.reports.view')
            ->name('entitlements.report');
        Route::resource('entitlements', BadgeEntitlementController::class)
            ->except(['destroy']);
        Route::post('entitlements/{entitlement}/cancel', [BadgeEntitlementController::class, 'cancel'])
            ->name('entitlements.cancel');

        // ─── توزيع الأوسمة ───
        Route::resource('distributions', BadgeDistributionController::class)
            ->only(['index', 'store', 'show']);
        Route::post('distributions/{distribution}/cancel', [BadgeDistributionController::class, 'cancel'])
            ->name('distributions.cancel');

        // ─── الأوسمة الناقصة ───
        Route::post('shortages/distribute-student', [BadgeShortageController::class, 'distributeStudent'])
            ->name('shortages.distribute-student');
        Route::get('shortages', [BadgeShortageController::class, 'index'])
            ->name('shortages.index');
        Route::get('shortages/{shortage}', [BadgeShortageController::class, 'show'])
            ->name('shortages.show');
        Route::post('shortages/{shortage}/resolve', [BadgeShortageController::class, 'resolve'])
            ->name('shortages.resolve');

        // ─── جولات تحصيل الأوسمة ───
        Route::resource('collections', BadgeCollectionRoundController::class)
            ->except(['destroy']);
        Route::put('collections/items/{item}', [BadgeCollectionRoundController::class, 'updateItem'])
            ->name('collections.items.update');
        Route::post('collections/{collection}/complete', [BadgeCollectionRoundController::class, 'complete'])
            ->name('collections.complete');
        Route::post('collections/{collection}/cancel', [BadgeCollectionRoundController::class, 'cancel'])
            ->name('collections.cancel');

        // ─── سجل العمليات (التقارير مدمجة في لوحة الإحصائيات) ───
        Route::get('logs', [BadgeLogController::class, 'index'])
            ->middleware('permission:badges.logs.view')
            ->name('logs.index');
    });
