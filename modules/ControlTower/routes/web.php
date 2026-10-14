<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ControlTower\Http\Controllers\ControlTowerController;

Route::middleware(['web', 'auth', 'role:admin,logistics_admin,planner,auditor'])->prefix('control-tower')->name('control_tower.')->group(function () {
    Route::get('/', [ControlTowerController::class, 'index'])->name('index');
    Route::get('/promises', [ControlTowerController::class, 'index'])->name('promises');
});
