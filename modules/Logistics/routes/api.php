<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['api'])->prefix('api/logistics')->name('api.logistics.')->group(function () {
    // API endpoints for logistics
});
