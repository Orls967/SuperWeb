<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Fashion\Http\Controllers\FashionCollectionController;

Route::middleware(['web', 'auth', 'role:fashion_buyer,merchandiser,admin'])->prefix('fashion')->group(function (): void {
    Route::get('/collections', [FashionCollectionController::class, 'index'])->name('fashion.collections.index');
    Route::post('/collections', [FashionCollectionController::class, 'store'])->name('fashion.collections.store');
    Route::post('/orders/allocate', [FashionCollectionController::class, 'allocate'])->name('fashion.orders.allocate');
});
