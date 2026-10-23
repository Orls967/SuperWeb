<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Insurance\Http\Controllers\InsuranceController;

Route::middleware(['web', 'auth', 'role:admin,insurance_admin,underwriter,customer'])->prefix('insurance')->group(function () {
    Route::get('/', [InsuranceController::class, 'index'])->name('insurance.index');
    Route::post('/products/{product}/issue', [InsuranceController::class, 'issue'])->name('insurance.products.issue');
});
