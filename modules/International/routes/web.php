<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\International\Http\Controllers\InternationalController;

Route::middleware(['web', 'auth'])->prefix('international')->name('international.')->group(function () {
    Route::get('/', [InternationalController::class, 'index'])->name('index');
    Route::get('/jvs', [InternationalController::class, 'index'])->name('jvs');
});
