<?php

declare(strict_types=1);

namespace Modules\AutoServe;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\AutoServe\Application\Listeners\CreatePredictiveMaintenanceBooking;
use Modules\AutoServe\Application\Services\AutoServeFleetMaintenanceBooking;
use Modules\AutoServe\Application\Services\AutoServeTenantSalesProvider;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\Logistics\Contracts\FleetMaintenanceBooking;
use Modules\Shared\Application\MenuRegistry;
use Modules\Telematics\Domain\Events\VehicleAnomalyDetected;

class AutoServeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AutoServeTenantSalesProvider::class);
        $this->app->tag(AutoServeTenantSalesProvider::class, 'mall.tenant_sales_provider');
        $this->app->bind(
            FleetMaintenanceBooking::class,
            AutoServeFleetMaintenanceBooking::class
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'serve');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Relation::morphMap([
            'serve_estimate' => Estimate::class,
        ]);

        $registry = $this->app->make(MenuRegistry::class);
        $registry->addItem(
            label: 'Buat Booking',
            route: 'bookings.create',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            roles: [],
            order: 10,
            group: 'Otomotif',
            activePattern: 'bookings.*',
        );

        $registry->addItem(
            label: 'Jenis Servis',
            route: 'services.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
            roles: ['admin'],
            order: 20,
            group: 'Otomotif',
            activePattern: 'services.*',
        );

        $registry->addItem(
            label: 'Sparepart',
            route: 'spareparts.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
            roles: ['admin'],
            order: 21,
            group: 'Otomotif',
            activePattern: 'spareparts.*',
        );

        Event::listen(
            VehicleAnomalyDetected::class,
            CreatePredictiveMaintenanceBooking::class
        );
    }
}
