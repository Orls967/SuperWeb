<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Distribution\Http\Controllers\DistributionController;
use Modules\Distribution\Http\Controllers\DistributorPortalController;

Route::middleware(['web', 'auth', 'verified', 'role:admin,procurement,distributor,auditor'])
    ->prefix('distribution')
    ->name('distribution.')
    ->group(function () {
        Route::get('/', [DistributionController::class, 'index'])->name('index');
        Route::get('/{distributor}', [DistributionController::class, 'show'])->name('show');
        Route::middleware('role:admin,procurement')->group(function () {
            Route::post('/distributors', [DistributionController::class, 'storeDistributor'])->name('distributors.store');
            Route::post('/{distributor}/securities', [DistributionController::class, 'storeSecurity'])->name('securities.store');
            Route::post('/{distributor}/onboarding/submit', [DistributionController::class, 'submitOnboarding'])->name('onboarding.submit');
            Route::post('/{distributor}/onboarding/approve', [DistributionController::class, 'approveOnboarding'])->name('onboarding.approve');
            Route::post('/{distributor}/transition', [DistributionController::class, 'transition'])->name('transition');
            Route::post('/{distributor}/outlets', [DistributionController::class, 'storeOutlet'])->name('outlets.store');
            Route::post('/{distributor}/tier/evaluate', [DistributionController::class, 'evaluateTier'])->name('tier.evaluate');
            Route::post('/territories', [DistributionController::class, 'storeTerritory'])->name('territories.store');
            Route::post('/coverages', [DistributionController::class, 'storeCoverage'])->name('coverages.store');
            Route::post('/invoices', [DistributionController::class, 'storeInvoice'])->name('invoices.store');
            Route::post('/invoices/pay', [DistributionController::class, 'storePayment'])->name('invoices.pay');
            Route::post('/targets', [DistributionController::class, 'storeTarget'])->name('targets.store');
            Route::post('/scorecards', [DistributionController::class, 'storeScorecard'])->name('scorecards.store');
            Route::post('/sweep', [DistributionController::class, 'sweep'])->name('sweep');
        });
    });

// 42.6 Portal distributor (role: distributor)
Route::middleware(['web', 'auth', 'verified', 'role:distributor,admin'])
    ->prefix('portal/distributors')
    ->name('distribution.portal.')
    ->group(function () {
        Route::get('/', [DistributorPortalController::class, 'home'])->name('home');
    });
