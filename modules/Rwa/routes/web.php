<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Rwa\Http\Controllers\RwaController;

Route::middleware(['web', 'auth', 'role:admin,rwa_admin,asset_manager,investor'])->prefix('rwa')->group(function () {
    Route::get('/', [RwaController::class, 'index'])->name('rwa.index');
    Route::post('/assets/{asset}/distribute', [RwaController::class, 'distribute'])->name('rwa.assets.distribute');
});
