<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin|general_manager|manager|supervisor|teacher'])->group(function () {

    Route::middleware('permission:view sequential absences')->group(function () {
        Route::get('/attendance/sequential-absences', 'App\Http\Controllers\AttendanceController@sequentialAbsences')
            ->name('attendance.sequential-absences');
    });

    Route::middleware('permission:notify attendance')->group(function () {
        Route::post(
            '/attendance/sequential-absences/{student}/notify',
            'App\Http\Controllers\AttendanceController@notifyStudent'
        )
            ->name('attendance.sequential-absences.notify');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/attendance/report', 'App\Http\Controllers\AttendanceController@report')
            ->name('attendance.report');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/attendance/export/excel', 'App\Http\Controllers\AttendanceController@export')
            ->name('attendance.export.excel');
        Route::get('/attendance/export/monthly', 'App\Http\Controllers\AttendanceController@exportMonthly')
            ->name('attendance.export.monthly');
    });

    Route::middleware('role:admin|general_manager|manager|supervisor|teacher')->group(function () {
        Route::get('/attendance/create', 'App\Http\Controllers\AttendanceController@create')
            ->name('attendance.create');
        Route::post('/attendance', 'App\Http\Controllers\AttendanceController@store')
            ->name('attendance.store');
    });

    Route::middleware('role:admin|general_manager|manager|supervisor')->group(function () {
        Route::get('/attendance/{attendance}/edit', 'App\Http\Controllers\AttendanceController@edit')
            ->name('attendance.edit');
        Route::put('/attendance/{attendance}', 'App\Http\Controllers\AttendanceController@update')
            ->name('attendance.update');
    });
    Route::middleware('role:admin')->group(function () {
        Route::delete('/attendance/{attendance}', 'App\Http\Controllers\AttendanceController@destroy')
            ->name('attendance.destroy');
    });

    // ✅ الـ {attendance} parameter في الآخر دائماً
    Route::get('/attendance', 'App\Http\Controllers\AttendanceController@index')
        ->name('attendance.index');
    Route::get('/attendance/{attendance}', 'App\Http\Controllers\AttendanceController@show')
        ->name('attendance.show');
});
