<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Intercompany\Http\Controllers\IntercompanyController;

Route::middleware(['web', 'auth'])->prefix('intercompany')->name('intercompany.')->group(function () {
    Route::get('/', [IntercompanyController::class, 'index'])->name('index');
    Route::get('/transactions', [IntercompanyController::class, 'index'])->name('transactions');
});
