<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Hotel\Http\Controllers\HotelController;

Route::middleware(['web', 'auth', 'role:admin,hotel_admin,front_desk'])->prefix('hotel')->group(function () {
    Route::get('/', [HotelController::class, 'index'])->name('hotel.index');
    Route::post('/reservations/{reservation}/settle', [HotelController::class, 'settleFolio'])->name('hotel.reservations.settle');
});
