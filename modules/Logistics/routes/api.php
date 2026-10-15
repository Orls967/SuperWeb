<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Logistics\Http\Controllers\Api\LogisticsApiController;

/*
|--------------------------------------------------------------------------
| API v1 Logistics Routes
|--------------------------------------------------------------------------
|
| Semua endpoint memerlukan token Sanctum kecuali pelacakan publik.
| Rate limit: 60 req/min (authenticated), 30 req/min (public tracking).
| Docs: docs/API.md
|
*/

// Public tracking (no auth, rate limited 30/min)
Route::middleware(['api', 'throttle:30,1'])
    ->prefix('api/v1/logistics')
    ->name('api.v1.logistics.')
    ->group(function () {
        Route::get('/tracking/{tracking_number}', [LogisticsApiController::class, 'track'])
            ->name('tracking.show');
    });

// Authenticated API (Sanctum token, rate limited 60/min)
Route::middleware(['api', 'auth:sanctum', 'throttle:60,1'])
    ->prefix('api/v1/logistics')
    ->name('api.v1.logistics.')
    ->group(function () {
        // Quotes
        Route::post('/quotes', [LogisticsApiController::class, 'createQuote'])
            ->middleware('role:admin,customer,dispatcher,logistics_admin,shipper')
            ->name('quotes.store');

        // Shipments (CRUD)
        Route::middleware('role:admin,auditor,dispatcher,driver,hub_operator,logistics_admin,shipper')->group(function () {
            Route::get('/shipments', [LogisticsApiController::class, 'listShipments'])
                ->name('shipments.index');
            Route::get('/shipments/{tracking_number}', [LogisticsApiController::class, 'showShipment'])
                ->name('shipments.show');
        });

        Route::post('/shipments', [LogisticsApiController::class, 'createShipment'])
            ->middleware('role:admin,dispatcher,logistics_admin,shipper')
            ->name('shipments.store');
    });
