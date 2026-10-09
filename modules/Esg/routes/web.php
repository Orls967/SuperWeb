<?php

use Illuminate\Support\Facades\Route;
use Modules\Esg\Http\Controllers\EsgController;

Route::middleware(['auth'])->prefix('esg')->name('esg.')->group(function () {
    Route::get('/', [EsgController::class, 'index'])->name('index');
});
