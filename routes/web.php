<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\LandingController;

// Public Landing Page (accessible to all visitors)
Route::get('/', [LandingController::class, 'index'])->name('home');

// Public Guest Routes (login & registration)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Authenticated Protected Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/switch-organisation', [AuthController::class, 'switchOrganisation'])->name('organisation.switch');

    // 7 Core Navigation Workspaces
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/registration', [\App\Http\Controllers\RegistrationController::class, 'index'])->name('register.index');
    Route::post('/registration/questionnaire/save', [\App\Http\Controllers\RegistrationController::class, 'saveDraft'])->name('register.questionnaire.save');
    Route::post('/registration/questionnaire/submit', [\App\Http\Controllers\RegistrationController::class, 'submit'])->name('register.questionnaire.submit');
    Route::post('/registration/reassess', [\App\Http\Controllers\RegistrationController::class, 'reassess'])->name('register.reassess');
    Route::get('/registration/assessments/{id}', [\App\Http\Controllers\RegistrationController::class, 'showAssessment'])->name('register.assessment.show');
    Route::get('/comply', [DashboardController::class, 'comply'])->name('comply.index');
    Route::get('/renew', [DashboardController::class, 'renew'])->name('renew.index');
    Route::get('/tender', [DashboardController::class, 'tender'])->name('tender.index');
    Route::get('/documents', [DashboardController::class, 'documents'])->name('documents.index');
    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports.index');
    Route::get('/settings', [DashboardController::class, 'settings'])->name('settings.index');
});
