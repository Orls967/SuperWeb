<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Port\Http\Controllers\FleetPortController;
use Modules\Port\Http\Controllers\PortController;

Route::middleware(['web', 'auth', 'verified'])->prefix('port')->name('port.')->group(function (): void {
    Route::middleware(['role:admin,port_operator,shipping_line_agent'])->group(function (): void {
        Route::get('/', [PortController::class, 'index'])->name('index');
        Route::get('/berths', [PortController::class, 'berths'])->name('berths');
        Route::get('/yard', [PortController::class, 'yard'])->name('yard');
        Route::get('/billing', [PortController::class, 'billing'])->name('billing');

        // Vertical Slice Armada & Pelayaran Kapal (Fase 180)
        Route::get('/fleet', [FleetPortController::class, 'index'])->name('fleet.index');
        Route::get('/voyages', [FleetPortController::class, 'voyages'])->name('voyages.index');
        Route::get('/charter', [FleetPortController::class, 'charter'])->name('charter.index');
        Route::get('/services', [FleetPortController::class, 'services'])->name('services.index');
        Route::post('/simulate/tce', [FleetPortController::class, 'simulateTce'])->name('simulate.tce');
        Route::post('/simulate/malacca-incident', [FleetPortController::class, 'simulateMalaccaIncident'])->name('simulate.malacca');
    });
});
