<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Insurance\Http\Controllers\InsuranceClaimsWorkflowController;
use Modules\Insurance\Http\Controllers\InsuranceController;

Route::middleware(['web', 'auth', 'role:admin,insurance_admin,underwriter,customer'])->prefix('insurance')->group(function () {
    Route::get('/', [InsuranceController::class, 'index'])->name('insurance.index');
    Route::post('/products/{product}/issue', [InsuranceController::class, 'issue'])->name('insurance.products.issue');

    // Insurance Underwriting & Claims Deepening Routes
    Route::prefix('workflow')->name('insurance.workflow.')->group(function () {
        Route::get('/policies', [InsuranceClaimsWorkflowController::class, 'policies'])->name('policies');
        Route::get('/claims', [InsuranceClaimsWorkflowController::class, 'claims'])->name('claims');
        Route::get('/fraud-evaluations', [InsuranceClaimsWorkflowController::class, 'fraudEvaluations'])->name('fraud-evaluations');
        Route::get('/parametric-triggers', [InsuranceClaimsWorkflowController::class, 'parametricTriggers'])->name('parametric-triggers');
        Route::post('/issue-policy', [InsuranceClaimsWorkflowController::class, 'issuePolicy'])->name('issue-policy');
        Route::post('/trigger-parametric', [InsuranceClaimsWorkflowController::class, 'triggerParametric'])->name('trigger-parametric');
        Route::post('/evaluate-fraud', [InsuranceClaimsWorkflowController::class, 'evaluateFraud'])->name('evaluate-fraud');
        Route::post('/settle-claim', [InsuranceClaimsWorkflowController::class, 'settleClaim'])->name('settle-claim');
    });
});
