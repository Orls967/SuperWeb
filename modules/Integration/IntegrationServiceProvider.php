<?php

declare(strict_types=1);

namespace Modules\Integration;

use Illuminate\Support\ServiceProvider;
use Modules\Integration\Application\Services\IntegrationService;
use Modules\Integration\Console\Commands\AuditIntegrationCommand;
use Modules\Shared\Application\MenuRegistry;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntegrationService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/integration')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/integration', 'integration');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditIntegrationCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Integrasi API & EDI',
            route: 'integration.index',
            icon: 'code-bracket',
            roles: ['admin', 'auditor'],
            order: 69,
            group: 'Sistem & Keamanan',
            activePattern: 'integration*',
        );
    }
}
