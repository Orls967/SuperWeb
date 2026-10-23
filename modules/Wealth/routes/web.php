<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Wealth\Http\Controllers\WealthController;

Route::middleware(['web', 'auth', 'role:admin,wealth_manager,treasury,investor,customer'])->prefix('wealth')->group(function () {
    Route::get('/', [WealthController::class, 'index'])->name('wealth.index');
    Route::post('/profiles/{profile}/invest', [WealthController::class, 'invest'])->name('wealth.profiles.invest');
});
