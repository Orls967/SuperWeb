<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\TradeFinance\Http\Controllers\TradeFinanceController;

Route::middleware(['web', 'auth'])->prefix('trade-finance')->name('trade_finance.')->group(function () {
    Route::get('/', [TradeFinanceController::class, 'index'])->name('index');
    Route::get('/lcs', [TradeFinanceController::class, 'index'])->name('lcs');
});
