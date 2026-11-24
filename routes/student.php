<?php

use App\Http\Controllers\StudentController;
use App\Http\Controllers\FavoriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin|general_manager|manager|supervisor|teacher'])->group(function () {

    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::middleware('role:admin')->group(function () {
        Route::get('/students/export', [StudentController::class, 'export'])->name('students.export');
        Route::get('/students/excluded-review', [StudentController::class, 'excludedReview'])
            ->name('students.excluded-review');
    });

    Route::middleware('permission:view favorites')->group(function () {
        Route::post('/students/{student}/favorite', [FavoriteController::class, 'store'])
            ->name('students.favorite.store');
        Route::delete('/students/{student}/favorite', [FavoriteController::class, 'destroy'])
            ->name('students.favorite.destroy');
        Route::get('/favorites', [FavoriteController::class, 'index'])
            ->name('favorites.index');
    });

    Route::middleware('permission:create student')->group(function () {
        Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
        Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    });


    Route::middleware('permission:edit student')->group(function () {
        Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    });

    Route::middleware('role:admin')->group(function () {
        Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    });

    // ملحوظة: كان هنا مسار assign-circle يشير لدالة غير موجودة في الكنترولر (500 عند الاستدعاء) —
    // تعيين الحلقة يتم فعليًا من تحديث الطالب (للأدمن فقط) مع فحص الدور داخله.
});

// عرض طالب واحد وبيانات تبويباته: متاحان أيضًا لولي الأمر بدوره —
// السياسة وحدها تحسم من يرى أي طالب (الولي: أبناؤه غير المتوقفين فقط).
Route::middleware(['auth', 'verified', 'role:admin|general_manager|manager|supervisor|teacher|guardian'])->group(function () {
    Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('/students/{student}/tab-data', [StudentController::class, 'tabData'])->name('students.tab-data');
});
