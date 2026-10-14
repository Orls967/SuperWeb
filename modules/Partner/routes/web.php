<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Partner\Http\Controllers\PartnerController;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('partners')
    ->name('partners.')
    ->group(function () {
        Route::get('/', [PartnerController::class, 'index'])->middleware('role:admin,procurement,auditor')->name('index');
        Route::get('/{partner}', [PartnerController::class, 'show'])->middleware('role:admin,procurement,auditor')->name('show');
        Route::post('/', [PartnerController::class, 'store'])->middleware('role:admin,procurement')->name('store');
        Route::post('/{partner}/transition', [PartnerController::class, 'transition'])->middleware('role:admin,procurement')->name('transition');
    });
