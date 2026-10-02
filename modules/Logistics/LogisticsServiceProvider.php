<?php

declare(strict_types=1);

namespace Modules\Logistics;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Logistics\Application\Commands\InvoiceShippersCommand;
use Modules\Logistics\Application\Listeners\RecognizeFreightRevenueOnDelivery;
use Modules\Logistics\Console\Commands\AccrueDemurrageCommand;
use Modules\Logistics\Console\Commands\AuditBillingCommand;
use Modules\Logistics\Console\Commands\CheckCapacityCommand;
use Modules\Logistics\Console\Commands\DetectLateShipmentsCommand;
use Modules\Logistics\Console\Commands\PayCarriersCommand;
use Modules\Logistics\Console\Commands\SettleCodCommand;
use Modules\Logistics\Console\Commands\VerifyCustodyCommand;
use Modules\Logistics\Domain\Events\ShipmentDelivered;
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

        Event::listen(ShipmentDelivered::class, RecognizeFreightRevenueOnDelivery::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                InvoiceShippersCommand::class,
                CheckCapacityCommand::class,
                VerifyCustodyCommand::class,
                DetectLateShipmentsCommand::class,
                SettleCodCommand::class,
                PayCarriersCommand::class,
                AccrueDemurrageCommand::class,
                AuditBillingCommand::class,
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
            label: 'Exception & SLA',
            route: 'logistics.exceptions.index',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
            roles: ['admin', 'logistics_admin', 'dispatcher', 'hub_operator'],
            order: 87,
            group: 'Logistik',
            activePattern: 'logistics/exceptions*'
        );

        $registry->addItem(
            label: 'Dashboard COD',
            route: 'logistics.cod.index',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>',
            roles: ['admin', 'logistics_admin', 'dispatcher', 'hub_operator'],
            order: 88,
            group: 'Logistik',
            activePattern: 'logistics/cod*'
        );

        $registry->addItem(
            label: 'Carrier & Margin',
            route: 'logistics.carriers.index',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>',
            roles: ['admin', 'logistics_admin', 'dispatcher'],
            order: 89,
            group: 'Logistik',
            activePattern: 'logistics/carriers*'
        );

        $registry->addItem(
            label: 'Klaim Kargo',
            route: 'logistics.claims.index',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>',
            roles: ['admin', 'logistics_admin', 'dispatcher', 'shipper'],
            order: 90,
            group: 'Logistik',
            activePattern: 'logistics/claims*'
        );

        $registry->addItem(
            label: 'Demurrage & Detention',
            route: 'logistics.dd.index',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
            roles: ['admin', 'logistics_admin', 'dispatcher'],
            order: 91,
            group: 'Logistik',
            activePattern: 'logistics/demurrage*'
        );

        $registry->addItem(
            label: 'Bea Cukai',
            route: 'logistics.customs.index',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>',
            roles: ['admin', 'logistics_admin', 'dispatcher', 'shipper'],
            order: 92,
            group: 'Logistik',
            activePattern: 'logistics/customs*'
        );

        $registry->addItem(
            label: 'BBM & Biaya Truk',
            route: 'logistics.fuel.index',
            icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>',
            roles: ['admin', 'logistics_admin', 'dispatcher', 'driver'],
            order: 93,
            group: 'Logistik',
            activePattern: 'logistics/fuel*'
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
