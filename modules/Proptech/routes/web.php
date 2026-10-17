<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Proptech\Http\Controllers\ProptechController;

Route::middleware(['web', 'auth', 'role:admin,proptech_admin,facility_manager'])->prefix('proptech')->group(function () {
    Route::get('/', [ProptechController::class, 'index'])->name('proptech.index');
    Route::post('/zones/{zone}/bill', [ProptechController::class, 'generateBill'])->name('proptech.zones.bill');
});
