<?php

declare(strict_types=1);

namespace Modules\Rwa;

use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Rwa\Application\Services\RwaTokenService;

class RwaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RwaTokenService::class, function ($app) {
            return new RwaTokenService(
                $app->make(SimClockInterface::class),
                $app->make(LedgerService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
