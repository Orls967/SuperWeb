<?php

declare(strict_types=1);

namespace Modules\Egy;

use Illuminate\Support\ServiceProvider;
use Modules\Egy\Application\Services\ConsolidatedUtilityBillingService;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Egy\Application\Services\MicrogridAndResilienceService;
use Modules\Egy\Console\Commands\EgyAuditCommand;
use Modules\Egy\Console\Commands\EgyBillPropertiesCommand;
use Modules\Egy\Console\Commands\EgyCommitUnitsCommand;
use Modules\Egy\Console\Commands\EgyIngestReadingsCommand;
use Modules\Egy\Console\Commands\EgyIslandCommand;
use Modules\Egy\Console\Commands\EgyPlanBatteryCommand;
use Modules\Shared\Application\MenuRegistry;

class EgyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnergyGridAndMeteringService::class);
        $this->app->singleton(ConsolidatedUtilityBillingService::class);
        $this->app->singleton(MicrogridAndResilienceService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        if (file_exists(__DIR__.'/routes/api.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        }

        $this->loadViewsFrom(__DIR__.'/resources/views', 'egy');

        if ($this->app->runningInConsole()) {
            $this->commands([
                EgyAuditCommand::class,
                EgyIngestReadingsCommand::class,
                EgyBillPropertiesCommand::class,
                EgyCommitUnitsCommand::class,
                EgyIslandCommand::class,
                EgyPlanBatteryCommand::class,
            ]);
        }

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
