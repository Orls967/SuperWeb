<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Insurance\Http\Controllers\FullInsuranceController;
use Modules\Insurance\Http\Controllers\InsuranceClaimsWorkflowController;
use Modules\Insurance\Http\Controllers\InsuranceController;
use Modules\Insurance\Http\Controllers\InsurancePoliciesController;

Route::middleware(['web', 'auth'])->prefix('insurance')->group(function () {
    Route::middleware('role:admin,insurance_admin,underwriter,customer')->group(function () {
        Route::get('/', [InsuranceController::class, 'index'])->name('insurance.index');
        Route::get('/policies', [InsuranceClaimsWorkflowController::class, 'policies'])->name('insurance.policies');
        Route::post('/policies/issue', [InsurancePoliciesController::class, 'issue'])->name('insurance.policies.issue');
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

    // Lini 18 Asuransi & Reasuransi Penuh (Fase 156)
    Route::prefix('full')->name('insurance.full.')->group(function () {
        Route::middleware('role:admin,superadmin,underwriter,actuary,claims_adjuster,reinsurance_mgr')->group(function () {
            Route::get('/products', [FullInsuranceController::class, 'products'])->name('products.index');
            Route::get('/actuarial', [FullInsuranceController::class, 'actuarial'])->name('actuarial.index');
        });

        Route::middleware('role:admin,superadmin,underwriter,actuary,claims_adjuster,reinsurance_mgr,customer')->group(function () {
            Route::get('/policies', [FullInsuranceController::class, 'policies'])->name('policies.index');
            Route::get('/policies/{id}', [FullInsuranceController::class, 'policyDetail'])->name('policies.show');
            Route::get('/claims', [FullInsuranceController::class, 'claims'])->name('claims.index');
            Route::get('/claims/{id}', [FullInsuranceController::class, 'claimDetail'])->name('claims.show');
        });
    });
});
