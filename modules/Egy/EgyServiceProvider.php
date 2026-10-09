<?php

declare(strict_types=1);

namespace Modules\Egy;

use Illuminate\Support\ServiceProvider;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Shared\Application\MenuRegistry;

class EgyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnergyGridAndMeteringService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if ($this->app->bound(MenuRegistry::class)) {
            $this->app->make(MenuRegistry::class)->addItem(
                label: 'Energi & Utilitas',
                route: 'energy.index',
                icon: 'zap',
                roles: ['grid_operator', 'genco_trader', 'energy_auditor', 'renewable_dev'],
                order: 130,
            );
        }
    }
}
