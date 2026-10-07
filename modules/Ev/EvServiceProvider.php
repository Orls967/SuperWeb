<?php

declare(strict_types=1);

namespace Modules\Ev;

use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Ev\Application\Services\EvChargingService;

class EvServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EvChargingService::class, function ($app) {
            return new EvChargingService(
                $app->make(SimClockInterface::class),
                $app->make(DigitalTwinInterface::class),
                $app->make(LedgerService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
