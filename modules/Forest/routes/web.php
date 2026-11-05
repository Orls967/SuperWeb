<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Forest\Http\Controllers\ForestConcessionController;

Route::middleware(['web', 'auth'])->prefix('forest')->name('forest.')->group(function () {
    Route::get('/plots', [ForestConcessionController::class, 'index'])->name('plots.index')->middleware('role:admin,forester,superadmin');
    Route::get('/plots/{id}', [ForestConcessionController::class, 'plotDetail'])->name('plots.show')->middleware('role:admin,forester,superadmin');
    Route::get('/permits', [ForestConcessionController::class, 'permits'])->name('permits.index')->middleware('role:admin,forester,superadmin');
    Route::get('/logs', [ForestConcessionController::class, 'logs'])->name('logs.index')->middleware('role:admin,forester,timber_inspector,superadmin');
    Route::get('/sales', [ForestConcessionController::class, 'sales'])->name('sales.index')->middleware('role:admin,forester,timber_buyer,superadmin');
    Route::get('/restoration', [ForestConcessionController::class, 'restoration'])->name('restoration.index')->middleware('role:admin,forester,esg_officer,superadmin');
});
