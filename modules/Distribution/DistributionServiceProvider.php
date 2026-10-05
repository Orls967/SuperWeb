<?php

declare(strict_types=1);

namespace Modules\Distribution;

use Illuminate\Support\ServiceProvider;
use Modules\Distribution\Application\Services\DistributionService;
use Modules\Distribution\Console\Commands\AuditDistributionCommand;
use Modules\Shared\Application\MenuRegistry;

class DistributionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DistributionService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/distribution')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/distribution', 'distribution');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditDistributionCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Distributor',
            route: 'distribution.index',
            icon: 'users',
            roles: ['admin', 'distributor', 'procurement', 'auditor'],
            order: 56,
            group: 'Logistik',
            activePattern: 'distribution*',
        );
    }
}
