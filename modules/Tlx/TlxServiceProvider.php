<?php

declare(strict_types=1);

namespace Modules\Tlx;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;
use Modules\Tlx\Application\Services\TelecomNetworkAndIotService;

class TlxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TelecomNetworkAndIotService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

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
