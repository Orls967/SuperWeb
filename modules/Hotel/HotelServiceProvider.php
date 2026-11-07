<?php

declare(strict_types=1);

namespace Modules\Hotel;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\HotelFolioPort;
use Modules\Hotel\Application\Services\HotelFolioAdapter;
use Modules\Hotel\Application\Services\HotelOperationsService;
use Modules\Hotel\Application\Services\RoomBlockReserverService;
use Modules\Hotel\Console\Commands\AuditHotelCommand;
use Modules\Hotel\Console\Commands\HotelCloseDayCommand;
use Modules\Hotel\Console\Commands\HotelReportOrphansCommand;
use Modules\Hotel\Contracts\RoomBlockReserver;
use Modules\Shared\Application\MenuRegistry;

class HotelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        @ini_set('memory_limit', '1024M');
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        $this->app->bind(HotelFolioPort::class, HotelFolioAdapter::class);
        $this->app->bind(RoomBlockReserver::class, RoomBlockReserverService::class);
        $this->app->singleton(HotelOperationsService::class);
    }

    public function boot(): void
    {
        @ini_set('memory_limit', '1024M');
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

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
                HotelCloseDayCommand::class,
                HotelReportOrphansCommand::class,
            ]);
        }
    }
}
