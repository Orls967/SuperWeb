<?php

use Illuminate\Support\Facades\Route;
use Modules\Agri\Http\Controllers\AgriController;

Route::middleware(['auth'])->prefix('agri')->name('agri.')->group(function () {
    Route::get('/', [AgriController::class, 'index'])->name('index');
});
