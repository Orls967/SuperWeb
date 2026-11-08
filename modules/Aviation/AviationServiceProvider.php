<?php

declare(strict_types=1);

namespace Modules\Aviation;

use Illuminate\Support\ServiceProvider;
use Modules\Aviation\Application\Services\SeatInventoryReserverService;
use Modules\Aviation\Console\Commands\AuditAviationCommand;
use Modules\Aviation\Console\Commands\CargoManifestCommand;
use Modules\Aviation\Console\Commands\DispatchScanCommand;
use Modules\Aviation\Console\Commands\ExpireSeatHoldsCommand;
use Modules\Aviation\Console\Commands\GenerateFlightManifestCommand;
use Modules\Aviation\Console\Commands\MaintenanceForecastCommand;
use Modules\Aviation\Console\Commands\PurgePassengerPiiCommand;
use Modules\Aviation\Console\Commands\ReconcileSlotUsageCommand;
use Modules\Aviation\Console\Commands\RefundSettleCommand;
use Modules\Aviation\Console\Commands\SlotSeasonPlanCommand;
use Modules\Aviation\Console\Commands\SyncAviationExternalFactorsCommand;
use Modules\Aviation\Contracts\SeatInventoryReserver;
use Modules\Aviation\Ledger\AviationTransactionTypes;
use Modules\Banking\Application\Registry\TransactionTypeRegistry;

class AviationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SeatInventoryReserver::class, SeatInventoryReserverService::class);
    }

    public function boot(): void
    {
        TransactionTypeRegistry::register(AviationTransactionTypes::class);

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'aviation');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditAviationCommand::class,
                SlotSeasonPlanCommand::class,
                DispatchScanCommand::class,
                RefundSettleCommand::class,
                MaintenanceForecastCommand::class,
                CargoManifestCommand::class,
                ExpireSeatHoldsCommand::class,
                ReconcileSlotUsageCommand::class,
                SyncAviationExternalFactorsCommand::class,
                PurgePassengerPiiCommand::class,
                GenerateFlightManifestCommand::class,
            ]);
        }
    }
}
