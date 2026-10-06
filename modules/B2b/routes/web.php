<?php

use Illuminate\Support\Facades\Route;
use Modules\B2b\Http\Controllers\B2bController;

Route::middleware(['auth'])->prefix('b2b')->name('b2b.')->group(function () {
    Route::get('/', [B2bController::class, 'index'])->name('index');
});
