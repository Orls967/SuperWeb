<?php

declare(strict_types=1);

namespace Modules\Mining;

use Illuminate\Support\ServiceProvider;
use Modules\Mining\Application\Services\MiningFleetDispatchService;
use Modules\Mining\Console\Commands\AuditMiningCommand;

class MiningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MiningFleetDispatchService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'mining');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditMiningCommand::class,
            ]);
        }
    }
}
