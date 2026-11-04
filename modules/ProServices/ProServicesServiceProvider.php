<?php

declare(strict_types=1);

namespace Modules\ProServices;

use Illuminate\Support\ServiceProvider;
use Modules\ProServices\Application\Services\ProServicesProjectService;
use Modules\ProServices\Console\Commands\AuditProServicesCommand;

final class ProServicesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProServicesProjectService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'proservices');
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditProServicesCommand::class,
            ]);
        }
    }
}
