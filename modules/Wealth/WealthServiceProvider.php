<?php

declare(strict_types=1);

namespace Modules\Wealth;

use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Wealth\Application\Services\RoboAdvisorService;

class WealthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RoboAdvisorService::class, function ($app) {
            return new RoboAdvisorService(
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
