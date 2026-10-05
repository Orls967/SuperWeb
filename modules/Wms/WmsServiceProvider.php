<?php

declare(strict_types=1);

namespace Modules\Wms;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;
use Modules\Wms\Application\Services\WmsService;
use Modules\Wms\Console\Commands\AuditWmsCommand;

class WmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WmsService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/wms')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/wms', 'wms');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditWmsCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Gudang',
            route: 'wms.index',
            icon: 'warehouse',
            roles: ['admin', 'hub_operator', 'procurement', 'auditor'],
            order: 55,
            group: 'Logistik',
            activePattern: 'wms*',
        );
    }
}
