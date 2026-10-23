# Klasifikasi 345 Service Modul Integration

> **Referensi:** PROGRESS R4.4, KONSEP §A14.6, Temuan K-05 & K-06, DECISIONS §P9/R4.4.

Dokumen ini menetapkan pemetaan arsitektural dan klasifikasi resmi untuk 345 domain service di `modules/Integration/Application/Services`.
`IntegrationServiceProvider` telah dikosongkan dari binding singleton tanpa konfigurasi agar seluruh service auto-resolve secara bersih melalui service container Laravel. Pemindahan fisik file service ke modul pemilik domain dilakukan bertahap sesuai Fase R7.

## Ringkasan Klasifikasi

| Kategori | Jumlah Service | Catatan |
|---|---|---|
| `arsipkan/hapus (izin pemilik)` | 13 | Sesuai kriteria K-05/K-06 |
| `pertahankan di Integration` | 20 | Sesuai kriteria K-05/K-06 |
| `pindah ke modul Agri` | 6 | Sesuai kriteria K-05/K-06 |
| `pindah ke modul Asset` | 2 | Sesuai kriteria K-05/K-06 |
| `pindah ke modul B2b` | 5 | Sesuai kriteria K-05/K-06 |
| `pindah ke modul Contract` | 2 | Sesuai kriteria K-05/K-06 |
| `pindah ke modul Core` | 195 | Sesuai kriteria K-05/K-06 |
| `pindah ke modul Esg` | 18 | Sesuai kriteria K-05/K-06 |
| `pindah ke modul Hcm` | 23 | Sesuai kriteria K-05/K-06 |
| `pindah ke modul Insurance` | 3 | Sesuai kriteria K-05/K-06 |
| `pindah ke modul Logistics` | 9 | Sesuai kriteria K-05/K-06 |
| `pindah ke modul Manufacturing` | 3 | Sesuai kriteria K-05/K-06 |
| `pindah ke modul Pricing` | 16 | Sesuai kriteria K-05/K-06 |
| `pindah ke modul Procurement` | 5 | Sesuai kriteria K-05/K-06 |
| `pindah ke modul Treasury` | 25 | Sesuai kriteria K-05/K-06 |
| **Total** | **345** | **100% service terklasifikasi** |

## Daftar Lengkap 345 Service

| No | Nama Service | Klasifikasi | Modul Target | Rasional / Domain |
|---|---|---|---|---|
| 1 | `AcademicIndustryResearchService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 2 | `AdvancedAnalyticsGraphResearchService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 3 | `AdvancedSupplyOptimizationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 4 | `AgentHitlOrchestrationService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 5 | `AgentMarketplaceGovernedToolsService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 6 | `AgentSafetyGuardrailsService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 7 | `AiCostSustainabilityService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 8 | `AiDecisionAuditService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 9 | `AiForecastingSopService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 10 | `AiFraudAmlMeshService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 11 | `AiGenerativeCopilotService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 12 | `AiHumanAccountabilityService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 13 | `AiModelGuardrailService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 14 | `AiOptimizationEngineService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 15 | `AirlineNetworkService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 16 | `AnalyticsFederationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 17 | `AnalyticsModelMonitoringService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 18 | `ApiIntegrationQualityGatesService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 19 | `AquacultureExportService` | `pindah ke modul Agri` | `Agri` | Domain agrikultur, perikanan/kelautan, & komoditas pangan |
| 20 | `ArchitectureFitnessMonolithHealthService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 21 | `AssetAvailabilityProgramService` | `pindah ke modul Asset` | `Asset` | Domain pemeliharaan & manajemen aset |
| 22 | `AssetProjectCapitalLifecycleService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 23 | `AssetReliabilityService` | `pindah ke modul Asset` | `Asset` | Domain pemeliharaan & manajemen aset |
| 24 | `AutomationControlPlaneRemediationService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 25 | `AutonomousEnterpriseLadderService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 26 | `AutonomousFieldFleetService` | `pindah ke modul Logistics` | `Logistics` | Domain rantai pasok, logistik, pergudangan, & armada |
| 27 | `AviationService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 28 | `B2bIndustryEcosystemPlatformService` | `pindah ke modul B2b` | `B2b` | Domain ekosistem B2B, partner, & kemitraan |
| 29 | `BiodiversityLandUseProgramService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 30 | `BmtMicrofinanceService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 31 | `BoardGovernanceDoaService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 32 | `BoardManagementReportingIntegrityService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 33 | `BusinessContinuityCrisisService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 34 | `BusinessContinuityCrisisSimulationService` | `arsipkan/hapus (izin pemilik)` | `Arsip` | Prototipe simulasi spekulatif / mega skenario tanpa entitas domain riil |
| 35 | `BusinessEthicsAntiCorruptionService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 36 | `BusinessKpiObservabilityService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 37 | `BusinessLineTransitionPlansService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 38 | `BusinessProcessOrchestrationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 39 | `CampusEducationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 40 | `CapacityCertificationReadinessService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 41 | `CapitalAllocationPortfolioService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 42 | `CapitalFundingStrategyService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 43 | `CaseLoyaltyUnificationService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 44 | `CashForecastingLiquidityCommandService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 45 | `CircularBusinessModelsRevenueService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 46 | `CircularEconomyService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 47 | `CircularityWasteProgramService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 48 | `CircularityWaterNatureService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 49 | `ClimateAdaptationResilienceService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 50 | `ClimateDecarbonizationRoadmapService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 51 | `ClimateRiskInsuranceResilienceService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 52 | `ClimateTargetsAllocationService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 53 | `ClimateTransitionFinanceService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 54 | `CoalitionLoyaltyBreakageService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 55 | `ColdChainHighValueLogisticsService` | `pindah ke modul Logistics` | `Logistics` | Domain rantai pasok, logistik, pergudangan, & armada |
| 56 | `CommercialRevenueGrowthPlanningService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 57 | `CommonAuditReconciliationService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 58 | `CommunityUgcSocialCommerceService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 59 | `CommunityValueSocialProcurementService` | `pindah ke modul Procurement` | `Procurement` | Domain pengadaan barang/jasa & kolaborasi supplier |
| 60 | `CompensationBenefitsService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 61 | `CompensationGovernancePayEquityService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 62 | `ConcurrencyLockingService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 63 | `ConglomerateSimulationService` | `arsipkan/hapus (izin pemilik)` | `Arsip` | Prototipe simulasi spekulatif / mega skenario tanpa entitas domain riil |
| 64 | `ContinuousControlsMonitoringService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 65 | `CorporateTaxEngineService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 66 | `CorporateVentureIncubationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 67 | `CostCapacityValueGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 68 | `CostEfficiencyPerformanceService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 69 | `CpqOrderToCashService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 70 | `CrisisContinuityService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 71 | `CrisisMegaScenarioService` | `arsipkan/hapus (izin pemilik)` | `Arsip` | Prototipe simulasi spekulatif / mega skenario tanpa entitas domain riil |
| 72 | `CriticalLeadershipCoverageService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 73 | `CrossBorderPayrollService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 74 | `CrossEcosystemWave2OrchestrationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 75 | `CrossLineProcessMeshService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 76 | `CrossLineServiceBundlesService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 77 | `CryptoNativeDefiSimulationService` | `arsipkan/hapus (izin pemilik)` | `Arsip` | Prototipe simulasi spekulatif / mega skenario tanpa entitas domain riil |
| 78 | `CustomerCrm360Service` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 79 | `CustomerDataPlatformActivationService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 80 | `CustomerLoyaltySubscriptionEconomyService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 81 | `CustomerPricingFairnessService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 82 | `CustomerServiceAgenticCommerceService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 83 | `CxMetricsVocOrchestrationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 84 | `CyberRansomwareRecoveryDrillService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 85 | `CyberResilienceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 86 | `DataAccessDomainSelfService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 87 | `DataGovernanceLineageService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 88 | `DataMeshFederatedGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 89 | `DataModelOpsGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 90 | `DataPlatformService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 91 | `DataProductsMonetizationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 92 | `DataQualityDriftLoadService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 93 | `DataResilienceMigrationService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 94 | `DataValueCostTransparencyService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 95 | `DatabasePartitionScaleService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 96 | `DecisionIntelligencePlatformService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 97 | `DecisionOptimizationGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 98 | `DesignSystemAccessibilityService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 99 | `DeveloperExperienceQualityService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 100 | `DeveloperProductivityEngineeringService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 101 | `DigitalProductExperimentationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 102 | `DigitalSecuritiesCapitalMarketsService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 103 | `DisasterRecoveryProofService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 104 | `DistributedTransactionSafetyService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 105 | `DistributorRetailerCollaborationService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 106 | `DocumentRecordAutomationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 107 | `DomainCapacityEnvelopesService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 108 | `DomainGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 109 | `DynamicMarketplaceC2cCommerceService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 110 | `EcosystemDaoGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 111 | `EcosystemHealthNetworkGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 112 | `EdgeModelRegistryRolloutService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 113 | `EducationCredentialAuthorizationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 114 | `EgovRegulatoryDigitalServicesService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 115 | `EmbeddedInsuranceService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 116 | `EndToEndFinanceService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 117 | `EndToEndOrderServiceOrchestrationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 118 | `EndToEndRiskService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 119 | `EndToEndSupplyChainService` | `pindah ke modul Logistics` | `Logistics` | Domain rantai pasok, logistik, pergudangan, & armada |
| 120 | `EndToEndTalentService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 121 | `EnergyDatacenterResilienceService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 122 | `EnterpriseAccountGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 123 | `EnterpriseAiGovernanceOperatingModelService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 124 | `EnterpriseArchitectureGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 125 | `EnterpriseCustomerValueGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 126 | `EnterpriseDataMigrationRetirementService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 127 | `EnterpriseDigitalTrustService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 128 | `EnterpriseEthicsSocialLicenseService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 129 | `EnterpriseFinancialControlAssuranceService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 130 | `EnterpriseFinancialIntegrityService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 131 | `EnterpriseFutureReadinessService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 132 | `EnterpriseIdentityZeroTrustService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 133 | `EnterpriseInnovationRdGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 134 | `EnterpriseLearningFlywheelService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 135 | `EnterpriseMasterDataGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 136 | `EnterpriseOperatingModelReadinessService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 137 | `EnterprisePerformanceManagementService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 138 | `EnterprisePolicyEngineService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 139 | `EnterprisePolicySimulationService` | `arsipkan/hapus (izin pemilik)` | `Arsip` | Prototipe simulasi spekulatif / mega skenario tanpa entitas domain riil |
| 140 | `EnterpriseProcessIntegrityService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 141 | `EnterpriseQualityGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 142 | `EnterpriseResilienceAntiFragilityService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 143 | `EnterpriseRiskAppetiteBoardReportingService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 144 | `EnterpriseRiskService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 145 | `EnterpriseSearchKnowledgeDocOpsService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 146 | `EnterpriseSearchKnowledgeGraphService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 147 | `EnterpriseServiceManagementCmdbService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 148 | `EnterpriseStakeholderValueReportingService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 149 | `EnterpriseSupplyChainGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 150 | `EnterpriseSustainabilityGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 151 | `EsgAssuranceDisclosureControlService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 152 | `EsgClimateDisclosureControlService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 153 | `EsgDataAssuranceDigitalReportingService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 154 | `EsgDataFabricService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 155 | `EsgHumanRightsJustTransitionService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 156 | `EsgImpactAuditScaleService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 157 | `EsgOperatingModelOwnershipService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 158 | `EthicalSourcingService` | `pindah ke modul Procurement` | `Procurement` | Domain pengadaan barang/jasa & kolaborasi supplier |
| 159 | `EthicsComplianceMaturityService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 160 | `EventDrivenCqrsSagaService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 161 | `EventSpineReplayCenterService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 162 | `FashionRetailCircularService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 163 | `FashionSourcingService` | `pindah ke modul Procurement` | `Procurement` | Domain pengadaan barang/jasa & kolaborasi supplier |
| 164 | `FederatedEdgeAiOperationsService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 165 | `FieldRemoteSiteOperationsIntegrityService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 166 | `FieldServiceSlaService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 167 | `FinanceCloseAgilityService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 168 | `FinancialCrimeSanctionsService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 169 | `FintechInsurtechPartnerService` | `pindah ke modul B2b` | `B2b` | Domain ekosistem B2B, partner, & kemitraan |
| 170 | `FleetAssetUtilizationOptimizationService` | `pindah ke modul Logistics` | `Logistics` | Domain rantai pasok, logistik, pergudangan, & armada |
| 171 | `FoodBrandNutritionService` | `pindah ke modul Agri` | `Agri` | Domain agrikultur, perikanan/kelautan, & komoditas pangan |
| 172 | `FoodProcessingService` | `pindah ke modul Agri` | `Agri` | Domain agrikultur, perikanan/kelautan, & komoditas pangan |
| 173 | `ForestryTimberService` | `pindah ke modul Agri` | `Agri` | Domain agrikultur, perikanan/kelautan, & komoditas pangan |
| 174 | `FpaDriverBasedBudgetingService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 175 | `FraudRiskAssessmentDetectionService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 176 | `FulfillmentOrchestrationPromiseService` | `pindah ke modul Logistics` | `Logistics` | Domain rantai pasok, logistik, pergudangan, & armada |
| 177 | `FullInsuranceService` | `pindah ke modul Insurance` | `Insurance` | Domain asuransi & kemitraan penjaminan |
| 178 | `GenerativeEngineeringCopilotService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 179 | `GlobalCommandService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 180 | `GlobalFinanceTreasuryRiskService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 181 | `GlobalPayrollCloseReconciliationService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 182 | `GlobalSearchService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 183 | `GoldenScenarioMegaAuditService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 184 | `GovEthicsAiBiometricsService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 185 | `GovInternalAuditAssuranceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 186 | `GovTrustServicesSignaturesService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 187 | `GovernanceDataRetentionDiscoveryService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 188 | `GreenProcurementLeaseChoiceService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 189 | `GreenProcurementSupplierDevelopmentService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 190 | `GroupCommandCenterService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 191 | `GroupFinanceOperatingModelService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 192 | `HealthInsuranceWellbeingService` | `pindah ke modul Insurance` | `Insurance` | Domain asuransi & kemitraan penjaminan |
| 193 | `HospitalityOperationsPlaybookService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 194 | `HumanAiCollaborationWorkflowsService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 195 | `IdentityTenancyService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 196 | `InstitutionalMemoryKnowledgeService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 197 | `InsuranceSyariahSecuritiesFinanceService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 198 | `IntegrationService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 199 | `InternalCapabilitiesMarketplaceService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 200 | `InternalControlMaturityService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 201 | `InternalControlSodService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 202 | `InvestorLenderReportingService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 203 | `InvestorRelationsService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 204 | `IslamicTradeFinanceService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 205 | `JourneyAnalyticsOptimizationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 206 | `KamPartnershipRevenueService` | `pindah ke modul B2b` | `B2b` | Domain ekosistem B2B, partner, & kemitraan |
| 207 | `KnowledgeGraphEnterpriseCopilotService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 208 | `KnowledgeGroundingCitationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 209 | `LeadershipPipelineExecutiveService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 210 | `LearningOperationsEffectivenessService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 211 | `LearningPlatformService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 212 | `LearningSkillIntelligenceService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 213 | `LegalOperationsService` | `pindah ke modul Contract` | `Contract` | Domain hukum & manajemen kontrak |
| 214 | `LegalRegulatoryChangeExecutionService` | `pindah ke modul Contract` | `Contract` | Domain hukum & manajemen kontrak |
| 215 | `LifeHealthWellnessService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 216 | `MaintenanceReliabilityGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 217 | `MarineAquacultureService` | `pindah ke modul Agri` | `Agri` | Domain agrikultur, perikanan/kelautan, & komoditas pangan |
| 218 | `MarketDisruptionSimulationService` | `arsipkan/hapus (izin pemilik)` | `Arsip` | Prototipe simulasi spekulatif / mega skenario tanpa entitas domain riil |
| 219 | `MarketingAutomationAttributionService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 220 | `MarketplaceDisputeBuyerProtectionService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 221 | `MediaCommerceContentRightsService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 222 | `MegaScenarioService` | `arsipkan/hapus (izin pemilik)` | `Arsip` | Prototipe simulasi spekulatif / mega skenario tanpa entitas domain riil |
| 223 | `MiningPortCommodityFlowService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 224 | `MnaDueDiligencePmiService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 225 | `MnaMegaScenarioRestructuringService` | `arsipkan/hapus (izin pemilik)` | `Arsip` | Prototipe simulasi spekulatif / mega skenario tanpa entitas domain riil |
| 226 | `ModelIncidentSafetyCaseService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 227 | `MultiModalVisionIntegrationService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 228 | `MultiRegionEdgeArchitectureService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 229 | `NatureFinanceService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 230 | `NatureWaterCommunityFinanceService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 231 | `NetworkResilienceChaosService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 232 | `ObservabilitySloCapacityService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 233 | `ObservabilitySloEconomyService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 234 | `OceanFleetService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 235 | `OmnichannelServiceConsistencySlaService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 236 | `OperationsExcellenceGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 237 | `OperationsExcellenceService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 238 | `OrgDesignChangeManagementService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 239 | `OrgDesignWorkforcePlanningService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 240 | `PartitionArchiveService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 241 | `PartnerApiEconomicsBillingService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 242 | `PartnerCosellAffiliateService` | `pindah ke modul B2b` | `B2b` | Domain ekosistem B2B, partner, & kemitraan |
| 243 | `PartnerOnboardingEcosystemService` | `pindah ke modul B2b` | `B2b` | Domain ekosistem B2B, partner, & kemitraan |
| 244 | `PerformanceCostOptimizationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 245 | `PerformanceEngagementAnalyticsService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 246 | `PerformanceRewardsTalentDecisionsService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 247 | `PlanningSchedulingUnificationService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 248 | `PlatformAutomatedOpsFinopsService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 249 | `PlatformEconomyService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 250 | `PlatformFeatureFlagsParityService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 251 | `PlatformFinalStressSecuritySimulationService` | `arsipkan/hapus (izin pemilik)` | `Arsip` | Prototipe simulasi spekulatif / mega skenario tanpa entitas domain riil |
| 252 | `PlatformMonolithEvolutionService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 253 | `PlatformRelease300HandoverService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 254 | `PlatformReleaseCandidateDocsService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 255 | `PolicyComplianceTestingRemediationService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 256 | `PortOperationsService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 257 | `PredictiveOperationsDigitalTwinService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 258 | `PricingScienceRevenueOptimizationService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 259 | `PrivacyEngineeringDataProductsService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 260 | `PrivacyVaultService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 261 | `ProblemManagementRootCauseService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 262 | `ProductLifecycleCircularDesignService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 263 | `ProductStewardshipEprService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 264 | `ProfessionalServicesService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 265 | `ProfitabilityCostIntelligenceService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 266 | `ProjectPortfolioManagementService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 267 | `PublicAffairsLicenseService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 268 | `QualityAssuranceInspectionProgramService` | `pindah ke modul Manufacturing` | `Manufacturing` | Domain manufaktur, QA inspeksi, & rekayasa kualitas |
| 269 | `QualityEngineeringAutomationService` | `pindah ke modul Manufacturing` | `Manufacturing` | Domain manufaktur, QA inspeksi, & rekayasa kualitas |
| 270 | `QualityManagementSystemService` | `pindah ke modul Manufacturing` | `Manufacturing` | Domain manufaktur, QA inspeksi, & rekayasa kualitas |
| 271 | `QueueSchedulerBatchService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 272 | `ReadWriteReplicaLagService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 273 | `RealtimeStreamProcessingService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 274 | `RegulatoryChangeMegaScenarioService` | `arsipkan/hapus (izin pemilik)` | `Arsip` | Prototipe simulasi spekulatif / mega skenario tanpa entitas domain riil |
| 275 | `RegulatoryComplianceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 276 | `RegulatoryPolicyLifecycleService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 277 | `RegulatoryTaxOperationsService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 278 | `ReinsuranceAndCatService` | `pindah ke modul Insurance` | `Insurance` | Domain asuransi & kemitraan penjaminan |
| 279 | `ReleaseManagementChangeAdvisoryService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 280 | `ReleaseTrainDeploymentSafetyService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 281 | `ResilienceWave2Service` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 282 | `RndTechTransferService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 283 | `SafetyCertifiedPermitAccessService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 284 | `SalesForcePipelineExcellenceService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 285 | `ScaleBenchmarkService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 286 | `SdmOrgHealthCultureService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 287 | `SdmWellnessOccupationalHealthService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 288 | `SecurityEngineeringSupplyChainIntegrityService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 289 | `SecurityPenTestService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 290 | `SecurityPenetrationRegressionService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 291 | `ServiceCatalogDeveloperPortalService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 292 | `ServiceLifecycleSunsetService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 293 | `ServiceOncallSloGovernanceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 294 | `ServiceRecoveryLoyaltyProtectionService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 295 | `SimulationSyntheticFactoryService` | `arsipkan/hapus (izin pemilik)` | `Arsip` | Prototipe simulasi spekulatif / mega skenario tanpa entitas domain riil |
| 296 | `SkillsEconomyInternalMobilityService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 297 | `SmartDistrictService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 298 | `SocialImpactCommunityProgramService` | `pindah ke modul Esg` | `Esg` | Domain keberlanjutan, dekarbonisasi, & tata kelola ESG |
| 299 | `StrategicPlanningExecutionService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 300 | `StrategicWorkforceCapabilityService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 301 | `StressBenchmarkBaselineService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 302 | `SubscriptionBillingRetentionService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 303 | `SubscriptionCommerceReplenishmentService` | `pindah ke modul Pricing` | `Pricing` | Domain penetapan harga, CRM, pelanggan, & komersial |
| 304 | `SukukAndZakatService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 305 | `SuperAppMiniAppPlatformService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 306 | `SupplierCollaborativeSourcingService` | `pindah ke modul Procurement` | `Procurement` | Domain pengadaan barang/jasa & kolaborasi supplier |
| 307 | `SupplierFinanceCollaborativePlanningService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 308 | `SupplyChainGovernanceScorecardsService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 309 | `SupplyChainNetworkService` | `pindah ke modul Logistics` | `Logistics` | Domain rantai pasok, logistik, pergudangan, & armada |
| 310 | `SupplyChainResilienceService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 311 | `SupplyChainTraceabilityService` | `pindah ke modul Logistics` | `Logistics` | Domain rantai pasok, logistik, pergudangan, & armada |
| 312 | `SupplyChainTradeMaterialFlowsService` | `pindah ke modul Logistics` | `Logistics` | Domain rantai pasok, logistik, pergudangan, & armada |
| 313 | `SustainabilityFinanceDataService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 314 | `SustainableFinanceReportingService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 315 | `SyariahBankingService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 316 | `SyariahOperationsService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 317 | `SyntheticDataTrainingGovernanceService` | `arsipkan/hapus (izin pemilik)` | `Arsip` | Prototipe simulasi spekulatif / mega skenario tanpa entitas domain riil |
| 318 | `TakafulAndAgriService` | `pindah ke modul Agri` | `Agri` | Domain agrikultur, perikanan/kelautan, & komoditas pangan |
| 319 | `TalentAcquisitionEmployerBrandService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 320 | `TalentLifecycleService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 321 | `TalentValueOrganizationalOutcomesService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 322 | `TaxCustomsTradeService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 323 | `TaxTransferPricingOperationsService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 324 | `TelecomIdentityService` | `pertahankan di Integration` | `Integration` | Adapter/konektor integrasi eksternal, protokol jaringan, atau gateway mitra |
| 325 | `TenderBidManagementService` | `pindah ke modul Procurement` | `Procurement` | Domain pengadaan barang/jasa & kolaborasi supplier |
| 326 | `TestDataComplianceService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 327 | `ThirdPartyEcosystemResilienceService` | `pindah ke modul Core` | `Core` | Platform resilience, business continuity, & disaster recovery Core |
| 328 | `ThirdPartyRiskService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 329 | `ThreatDetectionService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 330 | `TotalWellbeingSustainabilityService` | `pindah ke modul Core` | `Core` | Fondasi komputasi AI / model engine di platform Core |
| 331 | `TreasuryAlgorithmicMarketRiskService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 332 | `TreasuryUnificationService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 333 | `TrustSafetyModerationProtectionService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 334 | `UnifiedControlTowerDecisionService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 335 | `UserResearchDesignSystemService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 336 | `ValueChainSimulationService` | `arsipkan/hapus (izin pemilik)` | `Arsip` | Prototipe simulasi spekulatif / mega skenario tanpa entitas domain riil |
| 337 | `WarehouseRoboticsAutomationService` | `pindah ke modul Logistics` | `Logistics` | Domain rantai pasok, logistik, pergudangan, & armada |
| 338 | `WaterEnergyManagementService` | `pindah ke modul Core` | `Core` | Infrastruktur platform bersama, observability, arsitektur data Core |
| 339 | `WhistleblowingEthicsService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 340 | `WhistleblowingEthicsSpeakUpService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
| 341 | `WorkforceAutomationRoleDesignService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 342 | `WorkforceLaborComplianceIntegrityService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 343 | `WorkforceSkillsOperatingCapacityService` | `pindah ke modul Hcm` | `Hcm` | Domain human capital management, payroll, & workforce analytics |
| 344 | `WorkingCapitalScfService` | `pindah ke modul Treasury` | `Treasury` | Domain treasury, likuiditas, perpajakan, & manajemen modal |
| 345 | `ZeroTrustService` | `pindah ke modul Core` | `Core` | Domain keamanan siber, fraud/AML, GRC, & audit compliance Core platform |
