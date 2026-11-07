<?php

declare(strict_types=1);

namespace Modules\Mining;

use Illuminate\Support\ServiceProvider;
use Modules\Mining\Application\Services\BulkMarineVesselLogisticsGatewayService;
use Modules\Mining\Application\Services\ByProductResidueOfftakeGatewayService;
use Modules\Mining\Application\Services\MetalsTradingLmeGatewayService;
use Modules\Mining\Application\Services\MiningFleetDispatchService;
use Modules\Mining\Application\Services\PersonnelTelematicsGeofenceGatewayService;
use Modules\Mining\Application\Services\WarehouseReceiptRegistryGatewayService;
use Modules\Mining\Console\Commands\AuditGySamplingCommand;
use Modules\Mining\Console\Commands\AuditMiningCommand;
use Modules\Mining\Console\Commands\AuditMiningMetalsDeskCommand;
use Modules\Mining\Console\Commands\AuditMiningRoyaltyCommand;
use Modules\Mining\Console\Commands\AuditTailingsFacilityCommand;
use Modules\Mining\Console\Commands\AuditWeighbridgeHashChainCommand;
use Modules\Mining\Console\Commands\BenchmarkMineEmissionsCommand;
use Modules\Mining\Console\Commands\BlockReserveEstimateCommand;
use Modules\Mining\Console\Commands\ExportComplianceReportCommand;
use Modules\Mining\Console\Commands\ExportSmelterTradingReportCommand;
use Modules\Mining\Console\Commands\ExportUndergroundMarineReportCommand;
use Modules\Mining\Console\Commands\FuelAnomalyScanCommand;
use Modules\Mining\Console\Commands\IngestTelemetryCommand;
use Modules\Mining\Console\Commands\MtmMetalsTradingValuationCommand;
use Modules\Mining\Console\Commands\OeeBenchmarkScanCommand;
use Modules\Mining\Console\Commands\PhasePlanCommand;
use Modules\Mining\Console\Commands\PlanLayeredMetalsHedgingCommand;
use Modules\Mining\Console\Commands\RunSmelterPyrometallurgicalBalanceCommand;
use Modules\Mining\Console\Commands\ScanExpiredWarehouseReceiptsCommand;
use Modules\Mining\Console\Commands\ScanGeotechSlopeThresholdCommand;
use Modules\Mining\Console\Commands\ScanPermitExpiryCommand;
use Modules\Mining\Console\Commands\SettleFinalAssayInvoiceCommand;
use Modules\Mining\Console\Commands\ShiftCloseCommand;
use Modules\Mining\Console\Commands\SimulateVentilationNetworkCommand;
use Modules\Mining\Contracts\BulkMarineVesselLogisticsGateway;
use Modules\Mining\Contracts\ByProductResidueOfftakeGateway;
use Modules\Mining\Contracts\MetalsTradingLmeGateway;
use Modules\Mining\Contracts\PersonnelTelematicsGeofenceGateway;
use Modules\Mining\Contracts\WarehouseReceiptRegistryGateway;
use Modules\Shared\Application\MenuRegistry;

class MiningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MiningFleetDispatchService::class);
        $this->app->bind(
            PersonnelTelematicsGeofenceGateway::class,
            PersonnelTelematicsGeofenceGatewayService::class
        );
        $this->app->bind(
            BulkMarineVesselLogisticsGateway::class,
            BulkMarineVesselLogisticsGatewayService::class
        );
        $this->app->bind(
            MetalsTradingLmeGateway::class,
            MetalsTradingLmeGatewayService::class
        );
        $this->app->bind(
            WarehouseReceiptRegistryGateway::class,
            WarehouseReceiptRegistryGatewayService::class
        );
        $this->app->bind(
            ByProductResidueOfftakeGateway::class,
            ByProductResidueOfftakeGatewayService::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'mining');

        if ($this->app->bound(MenuRegistry::class)) {
            $this->app->make(MenuRegistry::class)->addItem(
                label: 'Pertambangan',
                route: 'mining.index',
                icon: 'truck',
                roles: ['mine_planner', 'fleet_dispatcher', 'mine_surveyor', 'hse_officer', 'royalty_officer'],
                order: 120,
            );
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditGySamplingCommand::class,
                AuditMiningCommand::class,
                AuditMiningRoyaltyCommand::class,
                AuditTailingsFacilityCommand::class,
                AuditWeighbridgeHashChainCommand::class,
                BenchmarkMineEmissionsCommand::class,
                BlockReserveEstimateCommand::class,
                ExportComplianceReportCommand::class,
                ExportUndergroundMarineReportCommand::class,
                FuelAnomalyScanCommand::class,
                IngestTelemetryCommand::class,
                OeeBenchmarkScanCommand::class,
                PhasePlanCommand::class,
                ScanGeotechSlopeThresholdCommand::class,
                ScanPermitExpiryCommand::class,
                ShiftCloseCommand::class,
                SimulateVentilationNetworkCommand::class,
                RunSmelterPyrometallurgicalBalanceCommand::class,
                MtmMetalsTradingValuationCommand::class,
                ExportSmelterTradingReportCommand::class,
                PlanLayeredMetalsHedgingCommand::class,
                SettleFinalAssayInvoiceCommand::class,
                ScanExpiredWarehouseReceiptsCommand::class,
                AuditMiningMetalsDeskCommand::class,
            ]);
        }
    }
}
