<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ProServices\Http\Controllers\ProServicesProjectController;

Route::middleware(['web', 'auth'])->prefix('proservices')->group(function () {
    Route::get('/projects', [ProServicesProjectController::class, 'indexProjects'])->name('proservices.projects.index');
    Route::get('/timesheets', [ProServicesProjectController::class, 'indexTimesheets'])->name('proservices.timesheets.index');
    Route::get('/timesheets/{id}', [ProServicesProjectController::class, 'showTimesheet'])->name('proservices.timesheets.show');

    Route::prefix('portal')->group(function () {
        Route::get('/invoices', [ProServicesProjectController::class, 'indexInvoices'])->name('proservices.portal.invoices');
        Route::get('/projects/{id}', [ProServicesProjectController::class, 'showClientProject'])->name('proservices.portal.projects.show');
    });
});
