<?php

declare(strict_types=1);

namespace Modules\Egy;

use Illuminate\Support\ServiceProvider;
use Modules\Egy\Application\Actions\SimulateBlackStartDrillAction;
use Modules\Egy\Application\Services\BessDegradationModelService;
use Modules\Egy\Application\Services\BessDegradationWarrantyAndCapacityFadeService;
use Modules\Egy\Application\Services\BessPeakShavingAndValueStackingService;
use Modules\Egy\Application\Services\CarbonAccountingAndGhgReportService;
use Modules\Egy\Application\Services\CarbonRetirementProofService;
use Modules\Egy\Application\Services\CarbonTradingAndEsgMarketplaceService;
use Modules\Egy\Application\Services\CbamComplianceReportingService;
use Modules\Egy\Application\Services\ConsolidatedUtilityBillingService;
use Modules\Egy\Application\Services\DemandResponsePortfolioAggregatorService;
use Modules\Egy\Application\Services\DroopControlAndFrequencyRegulationService;
use Modules\Egy\Application\Services\EmergencyPowerReadinessAuditService;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Egy\Application\Services\EsgLinkedPricingAndIncentiveService;
use Modules\Egy\Application\Services\GeneratorFuelStockAndMaintenanceScheduleService;
use Modules\Egy\Application\Services\MicrogridAndResilienceService;
use Modules\Egy\Application\Services\MicrogridIslandRestorationManagerService;
use Modules\Egy\Application\Services\MicrogridPowerFlowAndStateEstimationService;
use Modules\Egy\Application\Services\MicrogridScadaTelemetryProcessorService;
use Modules\Egy\Application\Services\ResilienceScorecardAndReliabilityIndexService;
use Modules\Egy\Console\Commands\EgyAuditCommand;
use Modules\Egy\Console\Commands\EgyBillPropertiesCommand;
use Modules\Egy\Console\Commands\EgyCarbonMatchCommand;
use Modules\Egy\Console\Commands\EgyCbamVerifyCommand;
use Modules\Egy\Console\Commands\EgyCommitUnitsCommand;
use Modules\Egy\Console\Commands\EgyConsolidateBillsCommand;
use Modules\Egy\Console\Commands\EgyDispatchHourCommand;
use Modules\Egy\Console\Commands\EgyEscoMvCommand;
use Modules\Egy\Console\Commands\EgyIngestReadingsCommand;
use Modules\Egy\Console\Commands\EgyIslandCommand;
use Modules\Egy\Console\Commands\EgyLeakDetectCommand;
use Modules\Egy\Console\Commands\EgyPlanBatteryCommand;
use Modules\Egy\Console\Commands\EgyRecMintCommand;
use Modules\Egy\Console\Commands\EgyShiftEvLoadCommand;
use Modules\Egy\Console\Commands\EgyWaterBalanceDailyCommand;
use Modules\Shared\Application\MenuRegistry;

class EgyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnergyGridAndMeteringService::class);
        $this->app->singleton(ConsolidatedUtilityBillingService::class);
        $this->app->singleton(MicrogridAndResilienceService::class);
        $this->app->singleton(CarbonTradingAndEsgMarketplaceService::class);
        $this->app->singleton(CarbonAccountingAndGhgReportService::class);
        $this->app->singleton(EsgLinkedPricingAndIncentiveService::class);
        $this->app->singleton(CbamComplianceReportingService::class);
        $this->app->singleton(CarbonRetirementProofService::class);
        $this->app->singleton(BessDegradationModelService::class);
        $this->app->singleton(DroopControlAndFrequencyRegulationService::class);
        $this->app->singleton(EmergencyPowerReadinessAuditService::class);
        $this->app->singleton(DemandResponsePortfolioAggregatorService::class);
        $this->app->singleton(MicrogridPowerFlowAndStateEstimationService::class);
        $this->app->singleton(ResilienceScorecardAndReliabilityIndexService::class);
        $this->app->singleton(BessPeakShavingAndValueStackingService::class);
        $this->app->singleton(GeneratorFuelStockAndMaintenanceScheduleService::class);
        $this->app->singleton(MicrogridScadaTelemetryProcessorService::class);
        $this->app->singleton(MicrogridIslandRestorationManagerService::class);
        $this->app->singleton(BessDegradationWarrantyAndCapacityFadeService::class);
        $this->app->singleton(SimulateBlackStartDrillAction::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        if (file_exists(__DIR__.'/routes/api.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        }

        $this->loadViewsFrom(__DIR__.'/resources/views', 'egy');

        if ($this->app->runningInConsole()) {
            $this->commands([
                EgyAuditCommand::class,
                EgyIngestReadingsCommand::class,
                EgyBillPropertiesCommand::class,
                EgyCarbonMatchCommand::class,
                EgyCbamVerifyCommand::class,
                EgyCommitUnitsCommand::class,
                EgyConsolidateBillsCommand::class,
                EgyDispatchHourCommand::class,
                EgyEscoMvCommand::class,
                EgyIslandCommand::class,
                EgyLeakDetectCommand::class,
                EgyPlanBatteryCommand::class,
                EgyRecMintCommand::class,
                EgyShiftEvLoadCommand::class,
                EgyWaterBalanceDailyCommand::class,
            ]);
        }

        if ($this->app->bound(MenuRegistry::class)) {
            $this->app->make(MenuRegistry::class)->addItem(
                label: 'Energi & Utilitas',
                route: 'energy.index',
                icon: 'zap',
                roles: ['grid_operator', 'genco_trader', 'energy_auditor', 'renewable_dev'],
                order: 130,
            );
        }
    }
}
