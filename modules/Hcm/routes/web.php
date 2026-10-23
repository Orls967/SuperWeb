<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Hcm\Http\Controllers\GigEconomyController;
use Modules\Hcm\Http\Controllers\HcmController;
use Modules\Hcm\Http\Controllers\HcmEnterpriseOperationsController;

Route::middleware(['web', 'auth', 'role:admin,hcm_manager'])->prefix('hcm')->group(function () {
    Route::get('/', [HcmController::class, 'index'])->name('hcm.index');

    Route::prefix('operations')->name('hcm.operations.')->group(function () {
        Route::get('/', [HcmEnterpriseOperationsController::class, 'index'])->name('index');
        Route::post('/department', [HcmEnterpriseOperationsController::class, 'createDepartment'])->name('department.create');
        Route::post('/job-grade', [HcmEnterpriseOperationsController::class, 'assignJobGrade'])->name('job_grade.assign');
        Route::post('/attendance', [HcmEnterpriseOperationsController::class, 'recordAttendance'])->name('attendance.record');
        Route::post('/payroll/{employee}/calculate', [HcmEnterpriseOperationsController::class, 'calculatePayroll'])->name('payroll.calculate');
        Route::post('/labor-allocation/{payroll}', [HcmEnterpriseOperationsController::class, 'allocateLabor'])->name('labor.allocate');
        Route::post('/audit/reconcile', [HcmEnterpriseOperationsController::class, 'runReconciliationAudit'])->name('audit.reconcile');
    });

    Route::prefix('bounties')->name('hcm.bounties.')->group(function () {
        Route::get('/', [GigEconomyController::class, 'index'])->name('index');
        Route::post('/', [GigEconomyController::class, 'store'])->name('store');
        Route::post('/{bounty}/claim', [GigEconomyController::class, 'claim'])->name('claim');
        Route::post('/claims/{claim}/payout', [GigEconomyController::class, 'payout'])->name('payout');
    });
});
