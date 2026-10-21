<?php

declare(strict_types=1);

namespace Modules\Hotel;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\HotelFolioPort;
use Modules\Hotel\Application\Services\HotelFolioAdapter;
use Modules\Hotel\Application\Services\HotelOperationsService;
use Modules\Hotel\Console\Commands\AuditHotelCommand;
use Modules\Shared\Application\MenuRegistry;

class HotelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HotelFolioPort::class, HotelFolioAdapter::class);
        $this->app->singleton(HotelOperationsService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'hotel');

        if ($this->app->bound(MenuRegistry::class)) {
            $this->app->make(MenuRegistry::class)->addItem(
                label: 'Perhotelan',
                route: 'hotel.index',
                icon: 'building',
                roles: ['front_office', 'housekeeping', 'revenue_mgr', 'hotel_gm', 'concierge'],
                order: 110,
            );
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditHotelCommand::class,
            ]);
        }
    }
}
