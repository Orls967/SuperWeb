<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Trade\Http\Controllers\TradeController;

Route::middleware(['web', 'auth'])->prefix('trade')->name('trade.')->group(function () {
    Route::get('/', [TradeController::class, 'index'])->name('index');
});
