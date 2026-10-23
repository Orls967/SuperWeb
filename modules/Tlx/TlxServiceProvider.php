<?php

declare(strict_types=1);

namespace Modules\Tlx;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;
use Modules\Tlx\Application\Services\TelecomNetworkAndIotService;
use Modules\Tlx\Console\Commands\TlxAuditCommand;

class TlxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TelecomNetworkAndIotService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'tlx');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TlxAuditCommand::class,
            ]);
        }

        if ($this->app->bound(MenuRegistry::class)) {
            $this->app->make(MenuRegistry::class)->addItem(
                label: 'Telekomunikasi & Data',
                route: 'telecom.index',
                icon: 'radio',
                roles: ['noc_engineer', 'dc_operator', 'iot_platform_mgr', 'network_planner'],
                order: 140,
            );
        }
    }
}
