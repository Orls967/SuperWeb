<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Party\Http\Controllers\PartyController;

Route::middleware(['web', 'auth', 'verified', 'role:admin,logistics_admin'])
    ->prefix('party')
    ->name('party.')
    ->group(function () {
        // Legal Entities
        Route::get('/legal-entities', [PartyController::class, 'legalEntities'])->name('legal-entities');
        Route::post('/legal-entities', [PartyController::class, 'storeLegalEntity'])->name('legal-entities.store');

        // Directory & CRUD
        Route::get('/', [PartyController::class, 'index'])->name('index');
        Route::get('/create', [PartyController::class, 'create'])->name('create');
        Route::post('/', [PartyController::class, 'store'])->name('store');
        Route::get('/{party}', [PartyController::class, 'show'])->name('show');

        // KYC
        Route::post('/{party}/kyc', [PartyController::class, 'submitKyc'])->name('kyc.submit');
        Route::post('/{party}/kyc/{doc}/approve', [PartyController::class, 'approveKyc'])->name('kyc.approve');
        Route::post('/{party}/kyc/{doc}/reject', [PartyController::class, 'rejectKyc'])->name('kyc.reject');

        // Sanctions & Credit
        Route::post('/{party}/screen', [PartyController::class, 'screen'])->name('screen');
        Route::post('/{party}/rescore', [PartyController::class, 'rescoreCredit'])->name('rescore');
    });
