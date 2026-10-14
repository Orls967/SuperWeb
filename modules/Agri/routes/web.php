<?php

use Illuminate\Support\Facades\Route;
use Modules\Agri\Http\Controllers\AgriController;

Route::middleware(['web', 'auth', 'role:admin,farmer,procurement'])->prefix('agri')->name('agri.')->group(function () {
    Route::get('/', [AgriController::class, 'index'])->name('index');
});
