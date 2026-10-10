<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\SchoolSwitchController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentDocumentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Middleware\ResolveSchoolContext;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/schools', [SchoolSwitchController::class, 'select'])->name('schools.select');
    Route::post('/schools/{school}/switch', [SchoolSwitchController::class, 'switch'])->name('schools.switch');

    Route::middleware(ResolveSchoolContext::class)->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Harus sebelum Route::resource('students') agar tidak tertangkap {student}.
        Route::get('/students/import', [StudentImportController::class, 'create'])->name('students.import.create');
        Route::post('/students/import', [StudentImportController::class, 'preview'])->name('students.import.preview');
        Route::post('/students/import/commit', [StudentImportController::class, 'commit'])->name('students.import.commit');
        Route::post('/students/import/cancel', [StudentImportController::class, 'cancel'])->name('students.import.cancel');

        Route::resource('students', StudentController::class);
        Route::post('/students/{student}/guardians', [StudentController::class, 'linkGuardian'])
            ->name('students.guardians.link');

        Route::post('/students/{student}/documents', [StudentDocumentController::class, 'store'])
            ->name('students.documents.store');
        Route::get('/documents/{document}/download', [StudentDocumentController::class, 'download'])
            ->name('documents.download');

        Route::resource('guardians', GuardianController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::resource('employees', EmployeeController::class);
    });
});
