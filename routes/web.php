<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\DashboardController;
use Modules\Shared\Http\Controllers\GlobalSearchController;

// ============================================================
// PUBLIC
// ============================================================
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('welcome');
});

// ============================================================
// AUTHENTICATED CORE ROUTES
// ============================================================
Route::middleware(['web', 'auth', 'verified'])->group(function () {
    // Dashboard (role-aware)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile (Breeze default)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Global Search (Ctrl+K)
    Route::get('/api/global-search', GlobalSearchController::class)->name('api.global-search');
});

require __DIR__.'/auth.php';
