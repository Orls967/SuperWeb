<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Hospital\Http\Controllers\HospitalController;

Route::middleware(['web', 'auth', 'role:admin,hospital_admin,doctor'])->prefix('hospital')->group(function () {
    Route::get('/', [HospitalController::class, 'index'])->name('hospital.index');
    Route::post('/trials/{trial}/bill-milestone', [HospitalController::class, 'billTrialMilestone'])->name('hospital.trials.bill');
});
