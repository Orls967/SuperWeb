<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryMovementExplorerController;

Route::middleware(['web', 'auth', 'role:admin,auditor,procurement'])->prefix('inventory/explorer')->name('inventory.explorer.')->group(function () {
    Route::get('/', [InventoryMovementExplorerController::class, 'index'])->name('index');
    Route::get('/{id}', [InventoryMovementExplorerController::class, 'show'])->name('show');
});
