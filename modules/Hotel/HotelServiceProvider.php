<?php

declare(strict_types=1);

namespace Modules\Hotel;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\HotelFolioPort;
use Modules\Hotel\Application\Services\HotelFolioAdapter;
use Modules\Hotel\Application\Services\HotelOperationsService;
use Modules\Hotel\Application\Services\HotelPackageAvailabilityService;
use Modules\Hotel\Application\Services\RoomBlockReserverService;
use Modules\Hotel\Application\TickHandlers\HotelNightAuditTickHandler;
use Modules\Hotel\Console\Commands\AuditHotelCommand;
use Modules\Hotel\Console\Commands\HotelApoaSubmitCommand;
use Modules\Hotel\Console\Commands\HotelAuditMiceAttritionCommand;
use Modules\Hotel\Console\Commands\HotelAuditMiceContractsCommand;
use Modules\Hotel\Console\Commands\HotelCloseDayCommand;
use Modules\Hotel\Console\Commands\HotelHousekeepingPlanCommand;
use Modules\Hotel\Console\Commands\HotelMiceMilestoneReminderCommand;
use Modules\Hotel\Console\Commands\HotelOverbookingPlanCommand;
use Modules\Hotel\Console\Commands\HotelRatePublishCommand;
use Modules\Hotel\Console\Commands\HotelReleaseRoomBlockAllotmentsCommand;
use Modules\Hotel\Console\Commands\HotelReportOrphansCommand;
use Modules\Hotel\Console\Commands\HotelSetbackScanCommand;
use Modules\Hotel\Console\Commands\HotelSyncBanquetBomProcurementCommand;
use Modules\Hotel\Contracts\HotelPackageAvailabilityValidator;
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
        $this->app->bind(HotelPackageAvailabilityValidator::class, HotelPackageAvailabilityService::class);
        $this->app->singleton(HotelOperationsService::class);
        $this->app->tag(HotelNightAuditTickHandler::class, 'sim.tick_handler');
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
                HotelRatePublishCommand::class,
                HotelOverbookingPlanCommand::class,
                HotelHousekeepingPlanCommand::class,
                HotelApoaSubmitCommand::class,
                HotelSetbackScanCommand::class,
                HotelReleaseRoomBlockAllotmentsCommand::class,
                HotelAuditMiceContractsCommand::class,
                HotelAuditMiceAttritionCommand::class,
                HotelSyncBanquetBomProcurementCommand::class,
                HotelMiceMilestoneReminderCommand::class,
            ]);
        }
    }
}
