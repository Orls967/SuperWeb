<?php

declare(strict_types=1);

namespace Modules\Mining;

use Illuminate\Support\ServiceProvider;
use Modules\Mining\Application\Services\MiningFleetDispatchService;
use Modules\Mining\Console\Commands\AuditGySamplingCommand;
use Modules\Mining\Console\Commands\AuditMiningCommand;
use Modules\Mining\Console\Commands\AuditMiningRoyaltyCommand;
use Modules\Mining\Console\Commands\AuditTailingsFacilityCommand;
use Modules\Mining\Console\Commands\AuditWeighbridgeHashChainCommand;
use Modules\Mining\Console\Commands\BenchmarkMineEmissionsCommand;
use Modules\Mining\Console\Commands\BlockReserveEstimateCommand;
use Modules\Mining\Console\Commands\ExportComplianceReportCommand;
use Modules\Mining\Console\Commands\FuelAnomalyScanCommand;
use Modules\Mining\Console\Commands\IngestTelemetryCommand;
use Modules\Mining\Console\Commands\OeeBenchmarkScanCommand;
use Modules\Mining\Console\Commands\PhasePlanCommand;
use Modules\Mining\Console\Commands\ScanPermitExpiryCommand;
use Modules\Mining\Console\Commands\ShiftCloseCommand;
use Modules\Shared\Application\MenuRegistry;

class MiningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MiningFleetDispatchService::class);
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
                FuelAnomalyScanCommand::class,
                IngestTelemetryCommand::class,
                OeeBenchmarkScanCommand::class,
                PhasePlanCommand::class,
                ScanPermitExpiryCommand::class,
                ShiftCloseCommand::class,
            ]);
        }
    }
}
