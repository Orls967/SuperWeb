<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\AutoServe\Http\Controllers\BookingController;
use Modules\AutoServe\Http\Controllers\InvoicePaymentController;
use Modules\AutoServe\Http\Controllers\ServiceController;
use Modules\AutoServe\Http\Controllers\SparepartController;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    // Booking
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::get('/bookings/{booking}/invoice', [BookingController::class, 'invoice'])->name('bookings.invoice');
    Route::post('/bookings/{booking}/pay', [InvoicePaymentController::class, 'pay'])->name('bookings.pay');
    Route::post('/bookings/{booking}/refund', [InvoicePaymentController::class, 'refund'])->name('bookings.refund');

    // Staff only (Admin & Mekanik)
    Route::middleware('role:admin,mekanik')->group(function () {
        Route::patch('/bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.updateStatus');
        Route::patch('/bookings/{booking}/assign', [BookingController::class, 'assignMechanic'])->name('bookings.assign');
        Route::post('/bookings/{booking}/spareparts', [BookingController::class, 'addSparepart'])->name('bookings.addSparepart');
        Route::delete('/bookings/{booking}/spareparts/{sparepart}', [BookingController::class, 'removeSparepart'])->name('bookings.removeSparepart');
    });

    // Admin only (Master Data)
    Route::middleware('role:admin')->group(function () {
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');

        Route::get('/spareparts', [SparepartController::class, 'index'])->name('spareparts.index');
        Route::post('/spareparts', [SparepartController::class, 'store'])->name('spareparts.store');
        Route::put('/spareparts/{sparepart}', [SparepartController::class, 'update'])->name('spareparts.update');
        Route::delete('/spareparts/{sparepart}', [SparepartController::class, 'destroy'])->name('spareparts.destroy');
    });
});
