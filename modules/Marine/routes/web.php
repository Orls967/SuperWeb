<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Marine\UI\Http\Controllers\MarineController;

Route::middleware(['web', 'auth', 'role:admin,farmer,manager,officer'])->prefix('marine')->group(function () {
    Route::get('/', [MarineController::class, 'index'])->name('marine.index');
    Route::get('/ponds/{id}', [MarineController::class, 'showPond'])->name('marine.ponds.show');
});
