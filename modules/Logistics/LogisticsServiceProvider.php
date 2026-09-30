<?php

declare(strict_types=1);

namespace Modules\Logistics;

use Illuminate\Support\ServiceProvider;
use Modules\Logistics\Application\Commands\InvoiceShippersCommand;
use Modules\Logistics\Console\Commands\CheckCapacityCommand;
use Modules\Shared\Application\MenuRegistry;

class LogisticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings for contracts will go here
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'logistics');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InvoiceShippersCommand::class,
                CheckCapacityCommand::class,
            ]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $registry = $this->app->make(MenuRegistry::class);

        $registry->addItem(
            label: 'Jaringan & Hub',
            route: 'logistics.locations.index',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>',
            roles: ['admin', 'logistics_admin', 'dispatcher'],
            order: 80,
            group: 'Logistik',
            activePattern: 'logistics/locations*'
        );

        $registry->addItem(
            label: 'Armada & Kontainer',
            route: 'logistics.fleet.index',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>',
            roles: ['admin', 'logistics_admin', 'dispatcher'],
            order: 81,
            group: 'Logistik',
            activePattern: 'logistics/fleet*'
        );

        $registry->addItem(
            label: 'Pengemudi & Kru',
            route: 'logistics.drivers.index',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
            roles: ['admin', 'logistics_admin', 'dispatcher'],
            order: 82,
            group: 'Logistik',
            activePattern: 'logistics/drivers*'
        );

        $registry->addItem(
            label: 'Pengiriman & Resi',
            route: 'logistics.shipments.index',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>',
            roles: ['admin', 'logistics_admin', 'dispatcher', 'hub_operator', 'shipper'],
            order: 83,
            group: 'Logistik',
            activePattern: 'logistics/shipments*'
        );

        $registry->addItem(
            label: 'Jadwal & Dispatch',
            route: 'logistics.dispatch.index',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>',
            roles: ['admin', 'logistics_admin', 'dispatcher'],
            order: 84,
            group: 'Logistik',
            activePattern: 'logistics/dispatch*'
        );

        $registry->addItem(
            label: 'Operasi Hub',
            route: 'logistics.hub.index',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>',
            roles: ['admin', 'logistics_admin', 'hub_operator'],
            order: 85,
            group: 'Logistik',
            activePattern: 'logistics/hub*'
        );

        $registry->addItem(
            label: 'Tugas Driver',
            route: 'logistics.driver.tasks',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>',
            roles: ['driver'],
            order: 86,
            group: 'Logistik',
            activePattern: 'logistics/driver*'
        );
    }
}
