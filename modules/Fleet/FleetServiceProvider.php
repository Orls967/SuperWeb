<?php

declare(strict_types=1);

namespace Modules\Fleet;

use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Fleet\Application\Services\FleetLeasingService;

class FleetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FleetLeasingService::class, function ($app) {
            return new FleetLeasingService(
                $app->make(LedgerService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
