<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Integration\Http\Controllers\IntegrationController;

Route::middleware(['web', 'auth'])->prefix('integration')->name('integration.')->group(function () {
    Route::get('/', [IntegrationController::class, 'index'])->name('index');
    Route::get('/webhooks', [IntegrationController::class, 'index'])->name('webhooks');
});
