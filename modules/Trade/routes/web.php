<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Trade\Http\Controllers\BiddingController;
use Modules\Trade\Http\Controllers\TradeController;
use Modules\Trade\Http\Controllers\TradeCustomsController;

Route::middleware(['web', 'auth', 'role:admin,auditor,procurement,treasury'])->prefix('trade')->name('trade.')->group(function () {
    Route::get('/', [TradeController::class, 'index'])->name('index');

    Route::prefix('bidding')->name('bidding.')->group(function () {
        Route::get('/', [BiddingController::class, 'index'])->name('index');
        Route::post('/agents', [BiddingController::class, 'createAgent'])->name('agents.store');
        Route::post('/runs', [BiddingController::class, 'evaluateRun'])->name('runs.evaluate');
        Route::post('/runs/{run}/prepare', [BiddingController::class, 'prepareSubmission'])->name('runs.prepare');
        Route::post('/submissions/{submission}/approve', [BiddingController::class, 'approveSubmission'])->name('submissions.approve');
        Route::post('/submissions/{submission}/result', [BiddingController::class, 'recordResult'])->name('submissions.result');
    });

    Route::prefix('customs')->name('customs.')->group(function () {
        Route::post('/import/{importOrder}/landed-cost', [TradeCustomsController::class, 'calculateLandedCost'])->name('landed_cost');
        Route::post('/import/{importOrder}/pib', [TradeCustomsController::class, 'submitPib'])->name('submit_pib');
        Route::post('/export/{exportOrder}/peb', [TradeCustomsController::class, 'submitPeb'])->name('submit_peb');
        Route::get('/bonded-zone/{importOrder}/audit', [TradeCustomsController::class, 'auditBondedZone'])->name('audit_bonded_zone');
    });
});
