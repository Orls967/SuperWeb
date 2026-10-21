<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Mining\Http\Controllers\MiningController;

Route::middleware(['web', 'auth', 'role:admin,mining_admin,site_manager,fleet_dispatcher,mine_surveyor,royalty_officer,hse_officer'])->prefix('mining')->group(function () {
    Route::get('/', [MiningController::class, 'index'])->name('mining.index');
    Route::post('/pits/{pit}/dispatch', [MiningController::class, 'dispatch'])->name('mining.dispatch');
});
