<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Hcm\Http\Controllers\HcmController;

Route::middleware(['web', 'auth'])->prefix('hcm')->group(function () {
    Route::get('/', [HcmController::class, 'index'])->name('hcm.index');
});
