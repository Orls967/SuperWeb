<?php

declare(strict_types=1);

namespace Modules\Port;

use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Registry\TransactionTypeRegistry;
use Modules\Logistics\Contracts\VesselRegistry;
use Modules\Port\Application\Services\CommercialCharterService;
use Modules\Port\Application\Services\GeneralAverageAdjustmentService;
use Modules\Port\Application\Services\MarineServicesDisbursementService;
use Modules\Port\Application\Services\MaritimeEsgCiiService;
use Modules\Port\Application\Services\PortFleetLedgerService;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Application\Services\VoyageExecutionService;
use Modules\Port\Console\Commands\AuditPortCommand;
use Modules\Port\Console\Commands\AuditPortFleetCommand;
use Modules\Port\Contracts\VesselCallWindowProvider;
use Modules\Port\Ledger\PortTransactionTypes;

class PortServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings kontrak lintas modul
        $this->app->singleton(PortFleetService::class);
        $this->app->bind(VesselRegistry::class, PortFleetService::class);

        $this->app->singleton(MarineServicesDisbursementService::class);
        $this->app->bind(VesselCallWindowProvider::class, MarineServicesDisbursementService::class);

        $this->app->singleton(VoyageExecutionService::class);
        $this->app->singleton(CommercialCharterService::class);
        $this->app->singleton(MaritimeEsgCiiService::class);
        $this->app->singleton(GeneralAverageAdjustmentService::class);
        $this->app->singleton(PortFleetLedgerService::class);
        $this->app->singleton(\Modules\Port\Application\Services\VesselMaintenanceWorkOrderService::class);
        $this->app->singleton(\Modules\Port\Application\Services\MaritimeCargoCustodyService::class);
        $this->app->singleton(\Modules\Port\Application\Services\BunkerProcurementAndHedgingService::class);
        $this->app->singleton(\Modules\Port\Application\Services\CanalTransitAndPassageTollService::class);
        $this->app->singleton(\Modules\Port\Application\Services\MaritimeInsuranceClaimService::class);
        $this->app->singleton(\Modules\Port\Application\Services\CrewRotationAndPayrollService::class);
    }

    public function boot(): void
    {
        TransactionTypeRegistry::register(PortTransactionTypes::class);

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'port');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditPortCommand::class,
                AuditPortFleetCommand::class,
            ]);
        }
    }
}
