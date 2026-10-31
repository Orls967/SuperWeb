<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Edu\Http\Controllers\EduAcademyController;
use Modules\Edu\Http\Controllers\EduAdminProgramController;
use Modules\Edu\Http\Controllers\EduCertificationController;
use Modules\Edu\Http\Controllers\EduResearchGrantController;
use Modules\Edu\Http\Controllers\EduStudentPortalController;
use Modules\Edu\Http\Controllers\PublicCertificateVerificationController;

// Public verification endpoints
Route::get('/api/v1/edu/certificates/verify', [PublicCertificateVerificationController::class, 'verifyApi'])->name('edu.api.certificates.verify');
Route::get('/academy/certificates/verify', [PublicCertificateVerificationController::class, 'verifyWeb'])->name('edu.web.certificates.verify');

// Admin program management (RBAC guarded)
Route::middleware(['web', 'auth', 'role:academy_admin,admin'])->prefix('academy/admin')->group(function () {
    Route::get('/programs', [EduAdminProgramController::class, 'index'])->name('edu.admin.programs.index');
    Route::post('/programs', [EduAdminProgramController::class, 'store'])->name('edu.admin.programs.store');
});

// Student portal (Anti-IDOR protected)
Route::middleware(['web', 'auth', 'role:admin,student'])->prefix('academy/student')->group(function () {
    Route::get('/dashboard', [EduStudentPortalController::class, 'dashboard'])->name('edu.student.dashboard');
    Route::get('/certificates/{id}', [EduStudentPortalController::class, 'showCertificate'])->name('edu.student.certificates.show');
    Route::get('/attempts/{id}', [EduStudentPortalController::class, 'showAttempt'])->name('edu.student.attempts.show');
});

// Legacy education routes
Route::middleware(['web', 'auth', 'role:admin,edu_admin,instructor,cert_officer,corp_lnd'])->prefix('education')->group(function () {
    Route::get('/', [EduAcademyController::class, 'index'])->name('edu.academy.index');
    Route::get('/overview', [EduAcademyController::class, 'index'])->name('education.index');
    Route::get('/cohorts/{id}', [EduAcademyController::class, 'show'])->name('edu.academy.show');
    Route::post('/enroll', [EduAcademyController::class, 'enroll'])->name('edu.academy.enroll');

    Route::get('/certifications', [EduCertificationController::class, 'index'])->name('edu.certification.index');
    Route::get('/certifications/verify/{hash}', [EduCertificationController::class, 'verify'])->name('edu.certification.verify');
    Route::post('/certifications/issue', [EduCertificationController::class, 'issue'])->name('edu.certification.issue');

    Route::get('/grants', [EduResearchGrantController::class, 'index'])->name('edu.grants.index');
    Route::get('/grants/{id}', [EduResearchGrantController::class, 'show'])->name('edu.grants.show');
    Route::post('/grants/milestones/{milestoneId}/disburse', [EduResearchGrantController::class, 'disburseMilestone'])->name('edu.grants.disburse');
});
