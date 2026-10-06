<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Plm\Http\Controllers\PlmController;

Route::middleware(['web', 'auth'])->prefix('plm')->group(function () {
    Route::get('/', [PlmController::class, 'index'])->name('plm.index');
});
