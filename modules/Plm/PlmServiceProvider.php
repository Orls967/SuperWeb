<?php

declare(strict_types=1);

namespace Modules\Plm;

use Illuminate\Support\ServiceProvider;
use Modules\Plm\Application\Services\PlmService;
use Modules\Plm\Console\Commands\AuditPlmCommand;

class PlmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlmService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'plm');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditPlmCommand::class,
            ]);
        }
    }
}
