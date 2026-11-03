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
    Route::get('/comply', [\App\Http\Controllers\ComplianceRegisterController::class, 'index'])->name('comply.index');
    Route::get('/compliance/obligations/{id}', [\App\Http\Controllers\ComplianceRegisterController::class, 'show'])->name('compliance.obligations.show');
    Route::post('/compliance/obligations/{id}/assign', [\App\Http\Controllers\ComplianceRegisterController::class, 'assign'])->name('compliance.obligations.assign');
    Route::post('/compliance/obligations/{id}/transition', [\App\Http\Controllers\ComplianceRegisterController::class, 'transition'])->name('compliance.obligations.transition');
    Route::post('/compliance/obligations/{id}/schedule', [\App\Http\Controllers\ComplianceRegisterController::class, 'updateSchedule'])->name('compliance.obligations.schedule');

    // Statutory Filing Calendar & Deadline Engine (Milestone 3.2)
    Route::get('/comply/calendar', [\App\Http\Controllers\ComplianceCalendarController::class, 'index'])->name('comply.calendar');
    Route::get('/calendar', [\App\Http\Controllers\ComplianceCalendarController::class, 'index'])->name('calendar.index');
    Route::post('/compliance/filings/generate', [\App\Http\Controllers\ComplianceCalendarController::class, 'generate'])->name('compliance.filings.generate');
    Route::get('/compliance/filings/{id}', [\App\Http\Controllers\ComplianceCalendarController::class, 'show'])->name('compliance.filings.show');
    Route::post('/compliance/filings/{id}/assign', [\App\Http\Controllers\ComplianceCalendarController::class, 'assign'])->name('compliance.filings.assign');
    Route::post('/compliance/filings/{id}/transition', [\App\Http\Controllers\ComplianceCalendarController::class, 'transition'])->name('compliance.filings.transition');
    Route::post('/compliance/filings/{id}/evidence/link', [\App\Http\Controllers\ComplianceCalendarController::class, 'linkEvidence'])->name('compliance.filings.evidence.link');
    Route::delete('/compliance/filings/{id}/evidence/{documentId}/unlink', [\App\Http\Controllers\ComplianceCalendarController::class, 'unlinkEvidence'])->name('compliance.filings.evidence.unlink');
    Route::post('/compliance/filings/{id}/amend-due-date', [\App\Http\Controllers\ComplianceCalendarController::class, 'amendDueDate'])->name('compliance.filings.amend-due-date');

    // Licence, Certificate & Registration Renewal Management (Milestone 3.3)
    Route::get('/renew', [\App\Http\Controllers\RenewalManagementController::class, 'index'])->name('renew.index');
    Route::post('/renew/instruments', [\App\Http\Controllers\RenewalManagementController::class, 'store'])->name('renew.instruments.store');
    Route::get('/renew/instruments/{id}', [\App\Http\Controllers\RenewalManagementController::class, 'show'])->name('renew.instruments.show');
    Route::post('/renew/instruments/{id}/metadata', [\App\Http\Controllers\RenewalManagementController::class, 'updateMetadata'])->name('renew.instruments.metadata');
    Route::post('/renew/instruments/{id}/assign', [\App\Http\Controllers\RenewalManagementController::class, 'assign'])->name('renew.instruments.assign');
    Route::post('/renew/instruments/{id}/validity', [\App\Http\Controllers\RenewalManagementController::class, 'changeValidity'])->name('renew.instruments.validity');
    Route::post('/renew/instruments/{id}/attempts/start', [\App\Http\Controllers\RenewalManagementController::class, 'startAttempt'])->name('renew.attempts.start');
    Route::post('/renew/attempts/{attemptId}/transition', [\App\Http\Controllers\RenewalManagementController::class, 'transitionAttempt'])->name('renew.attempts.transition');
    Route::post('/renew/instruments/{id}/evidence/link', [\App\Http\Controllers\RenewalManagementController::class, 'linkEvidence'])->name('renew.evidence.link');
    Route::delete('/renew/instruments/{id}/evidence/{linkId}/unlink', [\App\Http\Controllers\RenewalManagementController::class, 'unlinkEvidence'])->name('renew.evidence.unlink');

    Route::get('/tender', [DashboardController::class, 'tender'])->name('tender.index');
    
    // Document Vault Workspace & Verification Routes
    Route::get('/documents', [\App\Http\Controllers\DocumentVaultController::class, 'index'])->name('documents.index');
    Route::post('/documents/upload', [\App\Http\Controllers\DocumentVaultController::class, 'upload'])->name('documents.upload');
    Route::get('/documents/{id}/download', [\App\Http\Controllers\DocumentVaultController::class, 'download'])->name('documents.download');
    Route::get('/documents/{id}/preview', [\App\Http\Controllers\DocumentVaultController::class, 'preview'])->name('documents.preview');
    Route::post('/documents/{id}/extract', [\App\Http\Controllers\DocumentVaultController::class, 'extract'])->name('documents.extract');
    Route::post('/documents/{id}/verify', [\App\Http\Controllers\DocumentVaultController::class, 'verify'])->name('documents.verify');
    Route::post('/documents/{id}/reject', [\App\Http\Controllers\DocumentVaultController::class, 'reject'])->name('documents.reject');
    Route::post('/documents/{id}/link', [\App\Http\Controllers\DocumentVaultController::class, 'link'])->name('documents.link');
    Route::delete('/documents/{id}/unlink', [\App\Http\Controllers\DocumentVaultController::class, 'unlink'])->name('documents.unlink');

    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports.index');
    Route::get('/settings', [DashboardController::class, 'settings'])->name('settings.index');
});
