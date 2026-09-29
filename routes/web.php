<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SparepartController;
use Illuminate\Support\Facades\Route;

// ============================================================
// PUBLIC
// ============================================================
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('welcome');
});

// ============================================================
// AUTHENTICATED (semua role)
// ============================================================
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard (role-aware)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile (Breeze default)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ============================================================
    // BOOKING: Customer bisa create, semua bisa lihat detail & invoice
    // ============================================================
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::get('/bookings/{booking}/invoice', [BookingController::class, 'invoice'])->name('bookings.invoice');

    // ============================================================
    // STAFF ONLY (Admin & Mekanik)
    // ============================================================
    Route::middleware('role:admin,mekanik')->group(function () {
        // Update status booking & assign mekanik
        Route::patch('/bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.updateStatus');
        Route::patch('/bookings/{booking}/assign', [BookingController::class, 'assignMechanic'])->name('bookings.assign');

        // Sparepart management dalam booking
        Route::post('/bookings/{booking}/spareparts', [BookingController::class, 'addSparepart'])->name('bookings.addSparepart');
        Route::delete('/bookings/{booking}/spareparts/{sparepart}', [BookingController::class, 'removeSparepart'])->name('bookings.removeSparepart');
    });

    // ============================================================
    // ADMIN ONLY: Master Data
    // ============================================================
    Route::middleware('role:admin')->group(function () {
        // Services CRUD
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');

        // Spareparts CRUD
        Route::get('/spareparts', [SparepartController::class, 'index'])->name('spareparts.index');
        Route::post('/spareparts', [SparepartController::class, 'store'])->name('spareparts.store');
        Route::put('/spareparts/{sparepart}', [SparepartController::class, 'update'])->name('spareparts.update');
        Route::delete('/spareparts/{sparepart}', [SparepartController::class, 'destroy'])->name('spareparts.destroy');
    });

    // ============================================================
    // AUTODEX: Ensiklopedia Mobil & Garasi Pribadi
    // ============================================================
    Route::prefix('autodex')->name('autodex.')->group(function () {
        // Katalog Mobil Global
        Route::get('/', [\App\Http\Controllers\CarCatalogController::class, 'index'])->name('index');
        Route::get('/car/{car:slug}', [\App\Http\Controllers\CarCatalogController::class, 'show'])->name('show');
        
        // Garasi & Wishlist User
        Route::get('/garage', [\App\Http\Controllers\GarageController::class, 'index'])->name('garage.index');
        Route::post('/garage/{car}', [\App\Http\Controllers\GarageController::class, 'toggleGarage'])->name('garage.toggle');
        Route::post('/wishlist/{car}', [\App\Http\Controllers\GarageController::class, 'toggleWishlist'])->name('wishlist.toggle');
    });
});

require __DIR__.'/auth.php';
