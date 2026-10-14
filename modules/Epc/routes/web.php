<?php

use Illuminate\Support\Facades\Route;
use Modules\Epc\Http\Controllers\EpcController;

Route::middleware(['web', 'auth', 'role:admin,epc_manager,asset_manager'])->prefix('epc')->name('epc.')->group(function () {
    Route::get('/', [EpcController::class, 'index'])->name('index');
});
