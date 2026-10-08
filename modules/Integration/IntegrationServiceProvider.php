<?php

declare(strict_types=1);

namespace Modules\Integration;

use Illuminate\Support\ServiceProvider;
use Modules\Integration\Application\Services\AquacultureExportService;
use Modules\Integration\Application\Services\BmtMicrofinanceService;
use Modules\Integration\Application\Services\CampusEducationService;
use Modules\Integration\Application\Services\CrisisContinuityService;
use Modules\Integration\Application\Services\CrossBorderPayrollService;
use Modules\Integration\Application\Services\DataPlatformService;
use Modules\Integration\Application\Services\EmbeddedInsuranceService;
use Modules\Integration\Application\Services\EthicalSourcingService;
use Modules\Integration\Application\Services\FoodBrandNutritionService;
use Modules\Integration\Application\Services\FoodProcessingService;
use Modules\Integration\Application\Services\ForestryTimberService;
use Modules\Integration\Application\Services\FullInsuranceService;
use Modules\Integration\Application\Services\GlobalCommandService;
use Modules\Integration\Application\Services\IntegrationService;
use Modules\Integration\Application\Services\IslamicTradeFinanceService;
use Modules\Integration\Application\Services\LearningPlatformService;
use Modules\Integration\Application\Services\LifeHealthWellnessService;
use Modules\Integration\Application\Services\MarineAquacultureService;
use Modules\Integration\Application\Services\MegaScenarioService;
use Modules\Integration\Application\Services\NatureFinanceService;
use Modules\Integration\Application\Services\PlatformEconomyService;
use Modules\Integration\Application\Services\PrivacyVaultService;
use Modules\Integration\Application\Services\RegulatoryComplianceService;
use Modules\Integration\Application\Services\ReinsuranceAndCatService;
use Modules\Integration\Application\Services\ResilienceWave2Service;
use Modules\Integration\Application\Services\SukukAndZakatService;
use Modules\Integration\Application\Services\SupplyChainResilienceService;
use Modules\Integration\Application\Services\SyariahBankingService;
use Modules\Integration\Application\Services\SyariahOperationsService;
use Modules\Integration\Application\Services\TakafulAndAgriService;
use Modules\Integration\Application\Services\ThreatDetectionService;
use Modules\Integration\Application\Services\ZeroTrustService;
use Modules\Integration\Console\Commands\ApiAuditCommand;
use Modules\Integration\Console\Commands\AuditIntegrationCommand;
use Modules\Integration\Console\Commands\DrAuditCommand;
use Modules\Integration\Console\Commands\SecurityAuditCommand;
use Modules\Shared\Application\MenuRegistry;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntegrationService::class);
        $this->app->singleton(ZeroTrustService::class);
        $this->app->singleton(PrivacyVaultService::class);
        $this->app->singleton(RegulatoryComplianceService::class);
        $this->app->singleton(ThreatDetectionService::class);
        $this->app->singleton(SecurityPenTestService::class);
        $this->app->singleton(ResilienceWave2Service::class);
        $this->app->singleton(DataPlatformService::class);
        $this->app->singleton(PlatformEconomyService::class);
        $this->app->singleton(MegaScenarioService::class);
        $this->app->singleton(GlobalCommandService::class);
        $this->app->singleton(CrossBorderPayrollService::class);
        $this->app->singleton(SupplyChainResilienceService::class);
        $this->app->singleton(EthicalSourcingService::class);
        $this->app->singleton(CrisisContinuityService::class);
        $this->app->singleton(FullInsuranceService::class);
        $this->app->singleton(ReinsuranceAndCatService::class);
        $this->app->singleton(EmbeddedInsuranceService::class);
        $this->app->singleton(LifeHealthWellnessService::class);
        $this->app->singleton(TakafulAndAgriService::class);
        $this->app->singleton(SyariahBankingService::class);
        $this->app->singleton(SukukAndZakatService::class);
        $this->app->singleton(BmtMicrofinanceService::class);
        $this->app->singleton(IslamicTradeFinanceService::class);
        $this->app->singleton(SyariahOperationsService::class);
        $this->app->singleton(CampusEducationService::class);
        $this->app->singleton(LearningPlatformService::class);
        $this->app->singleton(FoodProcessingService::class);
        $this->app->singleton(FoodBrandNutritionService::class);
        $this->app->singleton(MarineAquacultureService::class);
        $this->app->singleton(AquacultureExportService::class);
        $this->app->singleton(ForestryTimberService::class);
        $this->app->singleton(NatureFinanceService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/integration')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/integration', 'integration');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditIntegrationCommand::class,
                SecurityAuditCommand::class,
                DrAuditCommand::class,
                ApiAuditCommand::class,
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
            label: 'Integrasi API & EDI',
            route: 'integration.index',
            icon: 'code-bracket',
            roles: ['admin', 'auditor'],
            order: 69,
            group: 'Sistem & Keamanan',
            activePattern: 'integration*',
        );
    }
}
