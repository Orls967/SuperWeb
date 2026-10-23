<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\B2b\Http\Controllers\B2bController;

Route::middleware(['web', 'auth', 'role:admin,b2b_buyer,procurement'])->prefix('b2b')->name('b2b.')->group(function () {
    Route::get('/', [B2bController::class, 'index'])->name('index');
});
