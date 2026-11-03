<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Port\Http\Controllers\PortController;

Route::middleware(['web', 'auth', 'verified'])->prefix('port')->name('port.')->group(function (): void {
    Route::middleware(['role:admin,port_operator,shipping_line_agent'])->group(function (): void {
        Route::get('/', [PortController::class, 'index'])->name('index');
        Route::get('/berths', [PortController::class, 'berths'])->name('berths');
        Route::get('/yard', [PortController::class, 'yard'])->name('yard');
        Route::get('/billing', [PortController::class, 'billing'])->name('billing');
    });
});
