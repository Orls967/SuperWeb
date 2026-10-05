<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Partner\Http\Controllers\PartnerController;

Route::middleware(['web', 'auth', 'verified', 'role:admin,procurement,auditor'])
    ->prefix('partners')
    ->name('partners.')
    ->group(function () {
        Route::get('/', [PartnerController::class, 'index'])->name('index');
        Route::get('/{partner}', [PartnerController::class, 'show'])->name('show');
        Route::post('/', [PartnerController::class, 'store'])->name('store');
        Route::post('/{partner}/transition', [PartnerController::class, 'transition'])->name('transition');
    });
