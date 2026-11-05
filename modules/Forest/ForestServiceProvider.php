<?php

declare(strict_types=1);

namespace Modules\Forest;

use Illuminate\Support\ServiceProvider;
use Modules\Forest\Application\Services\ForestConcessionService;
use Modules\Forest\Console\Commands\AuditForestCommand;

final class ForestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ForestConcessionService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'forest');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditForestCommand::class,
            ]);
        }
    }
}
