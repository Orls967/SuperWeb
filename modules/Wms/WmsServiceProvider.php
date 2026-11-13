<?php

declare(strict_types=1);

namespace Modules\Wms;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Intercompany\Contracts\SubledgerTotalProvider;
use Modules\Shared\Application\MenuRegistry;
use Modules\Wms\Http\Policies\RobotMissionPolicy;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Application\Services\StockReservationService;
use Modules\Wms\Application\Services\WmsService;
use Modules\Wms\Console\Commands\AuditWmsCommand;
use Modules\Wms\Console\Commands\CycleCountPlanCommand;
use Modules\Wms\Console\Commands\PostOpeningBalanceCommand;
use Modules\Wms\Console\Commands\ReplenishWmsCommand;
use Modules\Wms\Console\Commands\ReservationSweepCommand;
use Modules\Wms\Console\Commands\RevalueCostCommand;
use Modules\Wms\Console\Commands\RoboticsAllocateCommand;
use Modules\Wms\Console\Commands\RoboticsAuditCommand;
use Modules\Wms\Console\Commands\RoboticsChargePlanCommand;
use Modules\Wms\Console\Commands\RoboticsDeadlockScanCommand;
use Modules\Wms\Console\Commands\RoboticsDispatchCommand;
use Modules\Wms\Console\Commands\RoboticsReservationSweepCommand;
use Modules\Wms\Console\Commands\RoboticsRoiCommand;
use Modules\Wms\Console\Commands\RoboticsRoiPlanCommand;
use Modules\Wms\Console\Commands\RoboticsSimulateCommand;
use Modules\Wms\Console\Commands\RoboticsTwoSourceAuditCommand;
use Modules\Wms\Console\Commands\RoboticsWavePlanCommand;
use Modules\Wms\Console\Commands\TransitSweepCommand;
use Modules\Wms\Console\Commands\UtilizationSnapshotCommand;
use Modules\Wms\Contracts\SimulatedDeviceAdapterInterface;
use Modules\Wms\Contracts\StockAvailabilityReader;
use Modules\Wms\Contracts\WarehouseValuationReader;
use Modules\Wms\Infrastructure\Adapters\SimulatedAmrDeviceAdapter;
use Modules\Wms\Infrastructure\Adapters\WarehouseValuationReaderAdapter;
use Modules\Wms\Infrastructure\Adapters\WmsSubledgerTotalAdapter;
use Modules\Wms\Infrastructure\Api\WarehouseResourceProvider;
use Modules\Wms\Infrastructure\Edi\WmsEdiDesadvHandler;

class WmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WmsService::class);
        $this->app->bind(StockAvailabilityReader::class, StockReservationService::class);
        $this->app->bind(WarehouseValuationReader::class, WarehouseValuationReaderAdapter::class);
        $this->app->bind(SimulatedDeviceAdapterInterface::class, SimulatedAmrDeviceAdapter::class);

        $this->app->tag(WarehouseResourceProvider::class, 'core.api_resource_provider');
        $this->app->tag(WmsEdiDesadvHandler::class, 'core.edi_document_handler');
        $this->app->tag(WmsSubledgerTotalAdapter::class, SubledgerTotalProvider::class);
    }

    public function boot(): void
    {
        Gate::policy(RobotMission::class, RobotMissionPolicy::class);

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/wms')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/wms', 'wms');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditWmsCommand::class,
                ReplenishWmsCommand::class,
                CycleCountPlanCommand::class,
                TransitSweepCommand::class,
                PostOpeningBalanceCommand::class,
                RevalueCostCommand::class,
                ReservationSweepCommand::class,
                UtilizationSnapshotCommand::class,
                RoboticsDispatchCommand::class,
                RoboticsAuditCommand::class,
                RoboticsRoiPlanCommand::class,
                RoboticsAllocateCommand::class,
                RoboticsReservationSweepCommand::class,
                RoboticsWavePlanCommand::class,
                RoboticsRoiCommand::class,
                RoboticsSimulateCommand::class,
                RoboticsTwoSourceAuditCommand::class,
                RoboticsChargePlanCommand::class,
                RoboticsDeadlockScanCommand::class,
            ]);
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
