<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\PaymentIntentInspectorController;

// Payment Hub routes
Route::middleware(['web', 'auth', 'role:admin,auditor,treasury'])->prefix('payment')->name('payment.')->group(function () {
    Route::prefix('inspector')->name('inspector.')->group(function () {
        Route::get('/', [PaymentIntentInspectorController::class, 'index'])->name('index');
        Route::get('/{uuid}', [PaymentIntentInspectorController::class, 'show'])->name('show');
    });
});
