<?php

declare(strict_types=1);

namespace Modules\Integration;

use Illuminate\Support\ServiceProvider;
use Modules\Integration\Application\Services\AgentHitlOrchestrationService;
use Modules\Integration\Application\Services\AiDecisionAuditService;
use Modules\Integration\Application\Services\AiForecastingSopService;
use Modules\Integration\Application\Services\AiFraudAmlMeshService;
use Modules\Integration\Application\Services\AiGenerativeCopilotService;
use Modules\Integration\Application\Services\AiModelGuardrailService;
use Modules\Integration\Application\Services\AiOptimizationEngineService;
use Modules\Integration\Application\Services\AirlineNetworkService;
use Modules\Integration\Application\Services\AnalyticsFederationService;
use Modules\Integration\Application\Services\AquacultureExportService;
use Modules\Integration\Application\Services\AssetReliabilityService;
use Modules\Integration\Application\Services\AviationService;
use Modules\Integration\Application\Services\BmtMicrofinanceService;
use Modules\Integration\Application\Services\BusinessContinuityCrisisService;
use Modules\Integration\Application\Services\CampusEducationService;
use Modules\Integration\Application\Services\CapitalFundingStrategyService;
use Modules\Integration\Application\Services\CaseLoyaltyUnificationService;
use Modules\Integration\Application\Services\CircularEconomyService;
use Modules\Integration\Application\Services\ConcurrencyLockingService;
use Modules\Integration\Application\Services\CrisisContinuityService;
use Modules\Integration\Application\Services\CrossBorderPayrollService;
use Modules\Integration\Application\Services\CustomerCrm360Service;
use Modules\Integration\Application\Services\CyberResilienceService;
use Modules\Integration\Application\Services\DataPlatformService;
use Modules\Integration\Application\Services\DomainGovernanceService;
use Modules\Integration\Application\Services\EmbeddedInsuranceService;
use Modules\Integration\Application\Services\EnterpriseRiskService;
use Modules\Integration\Application\Services\EthicalSourcingService;
use Modules\Integration\Application\Services\FashionRetailCircularService;
use Modules\Integration\Application\Services\FashionSourcingService;
use Modules\Integration\Application\Services\FieldServiceSlaService;
use Modules\Integration\Application\Services\FinanceCloseAgilityService;
use Modules\Integration\Application\Services\FoodBrandNutritionService;
use Modules\Integration\Application\Services\FoodProcessingService;
use Modules\Integration\Application\Services\ForestryTimberService;
use Modules\Integration\Application\Services\FullInsuranceService;
use Modules\Integration\Application\Services\GlobalCommandService;
use Modules\Integration\Application\Services\GlobalSearchService;
use Modules\Integration\Application\Services\GroupCommandCenterService;
use Modules\Integration\Application\Services\IdentityTenancyService;
use Modules\Integration\Application\Services\IntegrationService;
use Modules\Integration\Application\Services\InternalControlSodService;
use Modules\Integration\Application\Services\InvestorRelationsService;
use Modules\Integration\Application\Services\IslamicTradeFinanceService;
use Modules\Integration\Application\Services\LearningPlatformService;
use Modules\Integration\Application\Services\LegalOperationsService;
use Modules\Integration\Application\Services\LifeHealthWellnessService;
use Modules\Integration\Application\Services\MarineAquacultureService;
use Modules\Integration\Application\Services\MegaScenarioService;
use Modules\Integration\Application\Services\NatureFinanceService;
use Modules\Integration\Application\Services\OceanFleetService;
use Modules\Integration\Application\Services\PartitionArchiveService;
use Modules\Integration\Application\Services\PlatformEconomyService;
use Modules\Integration\Application\Services\PortOperationsService;
use Modules\Integration\Application\Services\PrivacyVaultService;
use Modules\Integration\Application\Services\ProfessionalServicesService;
use Modules\Integration\Application\Services\ProfitabilityCostIntelligenceService;
use Modules\Integration\Application\Services\ProjectPortfolioManagementService;
use Modules\Integration\Application\Services\QualityManagementSystemService;
use Modules\Integration\Application\Services\RegulatoryComplianceService;
use Modules\Integration\Application\Services\RegulatoryPolicyLifecycleService;
use Modules\Integration\Application\Services\ReinsuranceAndCatService;
use Modules\Integration\Application\Services\ResilienceWave2Service;
use Modules\Integration\Application\Services\RndTechTransferService;
use Modules\Integration\Application\Services\ScaleBenchmarkService;
use Modules\Integration\Application\Services\SmartDistrictService;
use Modules\Integration\Application\Services\SukukAndZakatService;
use Modules\Integration\Application\Services\SupplyChainNetworkService;
use Modules\Integration\Application\Services\SupplyChainResilienceService;
use Modules\Integration\Application\Services\SyariahBankingService;
use Modules\Integration\Application\Services\SyariahOperationsService;
use Modules\Integration\Application\Services\TakafulAndAgriService;
use Modules\Integration\Application\Services\TaxCustomsTradeService;
use Modules\Integration\Application\Services\TelecomIdentityService;
use Modules\Integration\Application\Services\ThirdPartyRiskService;
use Modules\Integration\Application\Services\ThreatDetectionService;
use Modules\Integration\Application\Services\TreasuryUnificationService;
use Modules\Integration\Application\Services\ValueChainSimulationService;
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
        $this->app->singleton(CircularEconomyService::class);
        $this->app->singleton(ProfessionalServicesService::class);
        $this->app->singleton(LegalOperationsService::class);
        $this->app->singleton(AviationService::class);
        $this->app->singleton(AirlineNetworkService::class);
        $this->app->singleton(PortOperationsService::class);
        $this->app->singleton(OceanFleetService::class);
        $this->app->singleton(FashionSourcingService::class);
        $this->app->singleton(FashionRetailCircularService::class);
        $this->app->singleton(TelecomIdentityService::class);
        $this->app->singleton(SmartDistrictService::class);
        $this->app->singleton(DomainGovernanceService::class);
        $this->app->singleton(ValueChainSimulationService::class);
        $this->app->singleton(TreasuryUnificationService::class);
        $this->app->singleton(IdentityTenancyService::class);
        $this->app->singleton(AnalyticsFederationService::class);
        $this->app->singleton(GroupCommandCenterService::class);
        $this->app->singleton(ScaleBenchmarkService::class);
        $this->app->singleton(PartitionArchiveService::class);
        $this->app->singleton(ConcurrencyLockingService::class);
        $this->app->singleton(GlobalSearchService::class);
        $this->app->singleton(AiModelGuardrailService::class);
        $this->app->singleton(AgentHitlOrchestrationService::class);
        $this->app->singleton(AiDecisionAuditService::class);
        $this->app->singleton(AiGenerativeCopilotService::class);
        $this->app->singleton(AiOptimizationEngineService::class);
        $this->app->singleton(AiFraudAmlMeshService::class);
        $this->app->singleton(AiForecastingSopService::class);
        $this->app->singleton(EnterpriseRiskService::class);
        $this->app->singleton(InternalControlSodService::class);
        $this->app->singleton(CyberResilienceService::class);
        $this->app->singleton(ThirdPartyRiskService::class);
        $this->app->singleton(BusinessContinuityCrisisService::class);
        $this->app->singleton(RegulatoryPolicyLifecycleService::class);
        $this->app->singleton(TaxCustomsTradeService::class);
        $this->app->singleton(FinanceCloseAgilityService::class);
        $this->app->singleton(CapitalFundingStrategyService::class);
        $this->app->singleton(InvestorRelationsService::class);
        $this->app->singleton(ProfitabilityCostIntelligenceService::class);
        $this->app->singleton(QualityManagementSystemService::class);
        $this->app->singleton(AssetReliabilityService::class);
        $this->app->singleton(SupplyChainNetworkService::class);
        $this->app->singleton(FieldServiceSlaService::class);
        $this->app->singleton(ProjectPortfolioManagementService::class);
        $this->app->singleton(RndTechTransferService::class);
        $this->app->singleton(CustomerCrm360Service::class);
        $this->app->singleton(CaseLoyaltyUnificationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\SubscriptionBillingRetentionService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\MarketingAutomationAttributionService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\OrgDesignWorkforcePlanningService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\CompensationBenefitsService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\TalentLifecycleService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\PerformanceEngagementAnalyticsService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\LearningSkillIntelligenceService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\EsgDataFabricService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\ClimateDecarbonizationRoadmapService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\CircularityWaterNatureService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\BoardGovernanceDoaService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\WhistleblowingEthicsService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\EcosystemDaoGovernanceService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\CorporateVentureIncubationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\InternalCapabilitiesMarketplaceService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\DigitalProductExperimentationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\DeveloperExperienceQualityService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\ReleaseTrainDeploymentSafetyService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\PerformanceCostOptimizationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\DesignSystemAccessibilityService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\DataGovernanceLineageService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\RealtimeStreamProcessingService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\AdvancedAnalyticsGraphResearchService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\DataProductsMonetizationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\PricingScienceRevenueOptimizationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\SalesForcePipelineExcellenceService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\KamPartnershipRevenueService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\TenderBidManagementService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\CpqOrderToCashService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\CxMetricsVocOrchestrationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\EndToEndSupplyChainService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\EndToEndFinanceService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\EndToEndRiskService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\EndToEndTalentService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\GoldenScenarioMegaAuditService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\ObservabilitySloEconomyService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\EventDrivenCqrsSagaService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\MultiRegionEdgeArchitectureService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\EnterpriseSearchKnowledgeGraphService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\PartnerCosellAffiliateService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\SupplierFinanceCollaborativePlanningService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\DistributorRetailerCollaborationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\EgovRegulatoryDigitalServicesService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\FintechInsurtechPartnerService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\AcademicIndustryResearchService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\DecisionIntelligencePlatformService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\DataMeshFederatedGovernanceService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\AutonomousEnterpriseLadderService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\SimulationSyntheticFactoryService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\HumanAiCollaborationWorkflowsService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\DigitalSecuritiesCapitalMarketsService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\CryptoNativeDefiSimulationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\FinancialCrimeSanctionsService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\CorporateTaxEngineService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\CashForecastingLiquidityCommandService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\OperationsExcellenceService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\PlanningSchedulingUnificationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\FleetAssetUtilizationOptimizationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\WarehouseRoboticsAutomationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\HospitalityOperationsPlaybookService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\OmnichannelServiceConsistencySlaService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\CommunityUgcSocialCommerceService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\CoalitionLoyaltyBreakageService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\SdmOrgHealthCultureService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\SdmWellnessOccupationalHealthService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\EsgImpactAuditScaleService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\ClimateAdaptationResilienceService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\EsgHumanRightsJustTransitionService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\ProductStewardshipEprService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\EnterprisePolicyEngineService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\GovernanceDataRetentionDiscoveryService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\GovTrustServicesSignaturesService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\GovInternalAuditAssuranceService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\GovEthicsAiBiometricsService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\PlatformMonolithEvolutionService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\PlatformFeatureFlagsParityService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\PlatformAutomatedOpsFinopsService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\PlatformFinalStressSecuritySimulationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\PlatformReleaseCandidateDocsService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\PlatformRelease300HandoverService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\AdvancedSupplyOptimizationService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\SupplierCollaborativeSourcingService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\ColdChainHighValueLogisticsService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\FulfillmentOrchestrationPromiseService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\CommercialRevenueGrowthPlanningService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\AutonomousFieldFleetService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\PredictiveOperationsDigitalTwinService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\NetworkResilienceChaosService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\TreasuryAlgorithmicMarketRiskService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\WorkingCapitalScfService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\ContinuousControlsMonitoringService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\FpaDriverBasedBudgetingService::class);
        $this->app->singleton(\Modules\Integration\Application\Services\DynamicMarketplaceC2cCommerceService::class);
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
