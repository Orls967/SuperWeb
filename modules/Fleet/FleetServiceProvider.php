<?php

declare(strict_types=1);

namespace Modules\Fleet;

use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Fleet\Application\Services\FleetLeasingService;
use Modules\Fleet\Console\Commands\AuditFleetCommand;
use Modules\Fleet\Console\Commands\AuditMobilityCommand;

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
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'fleet');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditFleetCommand::class,
                AuditMobilityCommand::class,
            ]);
        }
    }
}
