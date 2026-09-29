<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Payment Hub routes
Route::middleware(['web', 'auth'])->prefix('payment')->name('payment.')->group(function () {
    // Reserved for future payment callbacks and webhooks
});
