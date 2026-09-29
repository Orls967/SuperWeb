<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\PassportController;

Route::middleware(['web'])->group(function () {
    Route::get('/passport/{uuid}', [PassportController::class, 'show'])->name('passport.show');
});
