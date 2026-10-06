<?php

use Illuminate\Support\Facades\Route;
use Modules\Epc\Http\Controllers\EpcController;

Route::middleware(['auth'])->prefix('epc')->name('epc.')->group(function () {
    Route::get('/', [EpcController::class, 'index'])->name('index');
});
