<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Edu\Http\Controllers\EduAcademyController;
use Modules\Edu\Http\Controllers\EduCertificationController;
use Modules\Edu\Http\Controllers\EduResearchGrantController;

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
