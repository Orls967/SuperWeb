<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Treasury\Http\Controllers\TreasuryController;

Route::middleware(['web', 'auth', 'role:admin,auditor,treasury'])->prefix('treasury')->name('treasury.')->group(function () {
    Route::get('/', [TreasuryController::class, 'index'])->name('index');
});
