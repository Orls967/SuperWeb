<?php

declare(strict_types=1);

namespace Modules\Agency;

use Illuminate\Support\ServiceProvider;
use Modules\Agency\Application\Services\AgencyService;
use Modules\Agency\Console\Commands\AuditAgencyCommand;
use Modules\Shared\Application\MenuRegistry;

class AgencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AgencyService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/agency')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/agency', 'agency');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditAgencyCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Agensi',
            route: 'agency.index',
            icon: 'briefcase',
            roles: ['admin', 'agent', 'procurement', 'auditor'],
            order: 58,
            group: 'Logistik',
            activePattern: 'agency*',
        );
    }
}
