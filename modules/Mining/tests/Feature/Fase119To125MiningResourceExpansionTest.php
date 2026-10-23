<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Mining\Application\Actions\CoordinateIntegratedResourceCommandCenterSlaAction;
use Modules\Mining\Application\Actions\DispatchMiningSolarHybridPowerAndCarbonOffsetAction;
use Modules\Mining\Application\Actions\EvaluateHseFatigueMentalHealthAndContractorPermitAction;
use Modules\Mining\Application\Actions\ScheduleBargeTransshipmentAndExportDemurrageAction;
use Modules\Mining\Application\Actions\SettleSmelterMetalsTradingHedgeAndAssayFinalizationAction;
use Modules\Mining\Application\Actions\SimulateUndergroundDigitalTwinVentilationAndGeotechAction;
use Modules\Mining\Application\Actions\VerifyTailingsWaterQualityAndBiodiversityRehabilitationAction;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use RuntimeException;
use Tests\TestCase;

class Fase119To125MiningResourceExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected MiningSite $site;

    protected MiningPit $pit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-KOLAKA-01',
            'name' => 'Pomalaa High-Grade Nickel Concession',
            'commodity' => 'NICKEL_LATERITE',
            'location' => 'Kolaka, Southeast Sulawesi',
        ]);

        $this->pit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_code' => 'PIT-ALPHA-01',
            'name' => 'Alpha Ridge Pit',
            'target_production_ton' => 500000,
            'actual_production_ton' => 380000,
        ]);
    }

    public function test_119_blast_safety_interlock_blocks_detonation_when_personnel_in_radius(): void
    {
        $action = app(SimulateUndergroundDigitalTwinVentilationAndGeotechAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('detected within safety exclusion zone');

        // Blast at (-4.1234, 121.5678) with radius 500m
        // Worker at (-4.1235, 121.5679) is approx 15 meters away
        $action->handle(
            pitId: $this->pit->id,
            scheduledAt: date('Y-m-d 12:00:00'),
            blastLat: -4.1234,
            blastLng: 121.5678,
            safetyRadiusMeters: 500.0,
            holesCount: 30,
            explosivesKg: 950.0,
            activePersonnelCoordinates: [
                ['worker_id' => 'WRK-GEO-001', 'lat' => -4.1235, 'lng' => 121.5679],
            ]
        );
    }

    public function test_119_blast_detonates_successfully_when_safety_radius_clear(): void
    {
        $action = app(SimulateUndergroundDigitalTwinVentilationAndGeotechAction::class);

        // Worker at (-4.1500, 121.6000) is > 3 km away
        $result = $action->handle(
            pitId: $this->pit->id,
            scheduledAt: date('Y-m-d 12:00:00'),
            blastLat: -4.1234,
            blastLng: 121.5678,
            safetyRadiusMeters: 500.0,
            holesCount: 30,
            explosivesKg: 950.0,
            activePersonnelCoordinates: [
                ['worker_id' => 'WRK-GEO-002', 'lat' => -4.1500, 'lng' => 121.6000],
            ],
            displacementMm: 3.2,
            velocityMmDay: 1.1
        );

        $this->assertEquals('DETONATED', $result['blast']->status);
        $this->assertTrue($result['safety_cleared']);
        $this->assertEquals('NORMAL_MONITORING', $result['alert_level']);
    }

    public function test_119_geotech_slope_stability_triggers_evacuation_alarm_on_high_velocity(): void
    {
        $action = app(SimulateUndergroundDigitalTwinVentilationAndGeotechAction::class);

        $result = $action->handle(
            pitId: $this->pit->id,
            scheduledAt: date('Y-m-d 14:00:00'),
            blastLat: -4.1234,
            blastLng: 121.5678,
            safetyRadiusMeters: 500.0,
            activePersonnelCoordinates: [],
            displacementMm: 18.5,
            velocityMmDay: 7.2
        );

        $this->assertTrue($result['slope_reading']->evacuation_alarm_triggered);
        $this->assertEquals('CRITICAL_EVACUATE', $result['alert_level']);
    }

    public function test_120_work_permit_validates_active_status_and_geofence_boundary(): void
    {
        $action = app(EvaluateHseFatigueMentalHealthAndContractorPermitAction::class);

        $result = $action->handle(
            siteId: $this->site->id,
            permitType: 'HOT_WORK',
            workerPartyId: 'PARTY-WRK-001',
            validFrom: date('Y-m-d H:i:s', strtotime('-1 hour')),
            validUntil: date('Y-m-d H:i:s', strtotime('+4 hours')),
            workerLat: -4.12345,
            workerLng: 121.56785,
            allowedLat: -4.12340,
            allowedLng: 121.56780,
            allowedRadiusMeters: 100.0,
            continuousWorkHours: 5.0,
            priorSleepHours: 8.0
        );

        $this->assertEquals('ACTIVE', $result['permit']->status);
        $this->assertEquals('FIT_FOR_DUTY', $result['fatigue_log']->recommended_action);
        $this->assertTrue($result['contractor_eval']->tender_eligible);
    }

    public function test_120_expired_work_permit_throws_revocation_exception(): void
    {
        $action = app(EvaluateHseFatigueMentalHealthAndContractorPermitAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has expired. Access revoked');

        $action->handle(
            siteId: $this->site->id,
            permitType: 'CONFINED_SPACE',
            workerPartyId: 'PARTY-WRK-002',
            validFrom: date('Y-m-d H:i:s', strtotime('-6 hours')),
            validUntil: date('Y-m-d H:i:s', strtotime('-1 hour')),
            workerLat: -4.12340,
            workerLng: 121.56780,
            allowedLat: -4.12340,
            allowedLng: 121.56780
        );
    }

    public function test_120_worker_outside_geofence_throws_boundary_exception(): void
    {
        $action = app(EvaluateHseFatigueMentalHealthAndContractorPermitAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outside authorized permit zone');

        // Worker is ~500m away, radius is 50m
        $action->handle(
            siteId: $this->site->id,
            permitType: 'HEIGHT',
            workerPartyId: 'PARTY-WRK-003',
            validFrom: date('Y-m-d H:i:s', strtotime('-1 hour')),
            validUntil: date('Y-m-d H:i:s', strtotime('+4 hours')),
            workerLat: -4.12800,
            workerLng: 121.57200,
            allowedLat: -4.12340,
            allowedLng: 121.56780,
            allowedRadiusMeters: 50.0
        );
    }

    public function test_120_critical_operator_fatigue_prohibits_heavy_equipment_operation(): void
    {
        $action = app(EvaluateHseFatigueMentalHealthAndContractorPermitAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Critical operator fatigue score');

        // Continuous 12 hours with only 3 hours sleep -> fatigue score 94 (>= 80)
        $action->handle(
            siteId: $this->site->id,
            permitType: 'LOTO',
            workerPartyId: 'PARTY-WRK-004',
            validFrom: date('Y-m-d H:i:s', strtotime('-1 hour')),
            validUntil: date('Y-m-d H:i:s', strtotime('+4 hours')),
            workerLat: -4.12340,
            workerLng: 121.56780,
            allowedLat: -4.12340,
            allowedLng: 121.56780,
            continuousWorkHours: 12.0,
            priorSleepHours: 3.0
        );
    }

    public function test_120_contractor_safety_evaluation_scores_tender_eligibility(): void
    {
        $action = app(EvaluateHseFatigueMentalHealthAndContractorPermitAction::class);

        $result = $action->handle(
            siteId: $this->site->id,
            permitType: 'HOT_WORK',
            workerPartyId: 'PARTY-WRK-005',
            validFrom: date('Y-m-d H:i:s', strtotime('-1 hour')),
            validUntil: date('Y-m-d H:i:s', strtotime('+4 hours')),
            workerLat: -4.12340,
            workerLng: 121.56780,
            allowedLat: -4.12340,
            allowedLng: 121.56780,
            continuousWorkHours: 4.0,
            priorSleepHours: 8.0,
            contractorPartyId: 'CONT-HEAVY-OPS-01',
            contractorIncidents: 1
        );

        $this->assertEquals(85, $result['contractor_eval']->safety_score);
        $this->assertEquals('PREFERRED', $result['contractor_eval']->tier);
        $this->assertTrue($result['contractor_eval']->tender_eligible);
    }

    public function test_121_smelter_metallurgical_mass_balance_and_byproduct_slag_recovery(): void
    {
        $action = app(SettleSmelterMetalsTradingHedgeAndAssayFinalizationAction::class);

        $result = $action->handle(
            siteId: $this->site->id,
            oreInputTon: 2000.0,
            oreGradePct: 1.90,
            recoveryRatePct: 88.0,
            tradingTonnage: 30.0,
            entryPriceUsd: 18000,
            currentMarkPriceUsd: 19500
        );

        $this->assertEquals(33.44, $result['output_metal_ton']);
        $this->assertLessThan(2000.0, $result['output_metal_ton']);
        $this->assertEquals('COMPLETED', $result['smelter_run']->status);
        $this->assertGreaterThan(0, $result['smelter_run']->byproduct_revenue_idr);
    }

    public function test_121_metals_trading_mark_to_market_and_digital_warehouse_receipt(): void
    {
        $action = app(SettleSmelterMetalsTradingHedgeAndAssayFinalizationAction::class);

        $result = $action->handle(
            siteId: $this->site->id,
            tradingTonnage: 20.0,
            entryPriceUsd: 18000,
            currentMarkPriceUsd: 19000
        );

        $this->assertEquals(2000000, $result['trading_position']->unrealized_pnl_minor);
        $this->assertTrue($result['warehouse_receipt']->is_collateralized);
        $this->assertEquals('ISSUED', $result['warehouse_receipt']->status);
    }

    public function test_122_export_vessel_sanctions_screening_blocks_embargoed_destination(): void
    {
        $action = app(ScheduleBargeTransshipmentAndExportDemurrageAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('trade sanctions embargo');

        $action->handle(
            vesselName: 'MV Oceanic Voyager',
            destinationCountry: 'SANCTIONED_COUNTRY',
            loadedTonnage: 45000.0
        );
    }

    public function test_122_export_laytime_exceeded_levies_statutory_demurrage(): void
    {
        $action = app(ScheduleBargeTransshipmentAndExportDemurrageAction::class);

        // Allowed 72h, actual 96h -> 24h laytime exceeded @ 20,000,000 IDR/h = 480,000,000 IDR
        $result = $action->handle(
            vesselName: 'MV Pacific Pioneer',
            destinationCountry: 'JAPAN',
            loadedTonnage: 60000.0,
            allowedLaytimeHours: 72.0,
            actualLaytimeHours: 96.0,
            demurrageRatePerHourIdr: 20000000,
            sellerGradePct: 1.80,
            surveyorGradePct: 1.82,
            gradeTolerancePct: 0.05
        );

        $this->assertEquals(480000000, $result['demurrage_total_idr']);
        $this->assertEquals(480000000, $result['voyage']->demurrage_total_minor);
        $this->assertFalse($result['is_disputed']);
        $this->assertNull($result['dispute']);
    }

    public function test_122_assay_grade_variance_exceeding_tolerance_holds_payment(): void
    {
        $action = app(ScheduleBargeTransshipmentAndExportDemurrageAction::class);

        // Seller declares 1.85%, surveyor assays 1.65% (0.20% diff > 0.05% tolerance)
        $result = $action->handle(
            vesselName: 'MV Eastern Star',
            destinationCountry: 'SOUTH_KOREA',
            loadedTonnage: 55000.0,
            allowedLaytimeHours: 72.0,
            actualLaytimeHours: 70.0,
            sellerGradePct: 1.85,
            surveyorGradePct: 1.65,
            gradeTolerancePct: 0.05
        );

        $this->assertTrue($result['is_disputed']);
        $this->assertNotNull($result['dispute']);
        $this->assertTrue($result['dispute']->is_payment_held);
        $this->assertEquals('DISPUTED', $result['dispute']->status);
    }

    public function test_123_mining_solar_hybrid_dispatch_and_intercompany_clean_power_billing(): void
    {
        $action = app(DispatchMiningSolarHybridPowerAndCarbonOffsetAction::class);

        $result = $action->handle(
            siteId: $this->site->id,
            solarGenerationKwh: 200000.0,
            dieselConsumptionKwh: 300000.0,
            transferRatePerKwhIdr: 1500,
            consumerEntityId: 'SMELTER-UNIT-01'
        );

        $this->assertEquals(40.0, $result['solar_ratio_pct']);
        $this->assertEquals(170.0, $result['scope1_avoided_tons']);
        $this->assertEquals(300000000, $result['renewable_billing']->total_amount_minor);
    }

    public function test_123_verified_carbon_credit_issuance_and_green_nickel_premium(): void
    {
        $action = app(DispatchMiningSolarHybridPowerAndCarbonOffsetAction::class);

        $result = $action->handle(
            siteId: $this->site->id,
            reforestedAreaHectares: 300.0,
            verifiedNdviScore: 0.80,
            greenNickelTonnage: 400.0,
            greenPremiumAdderPerTonIdr: 20000000
        );

        $this->assertEquals(2400.0, $result['carbon_issuance']->issued_credits_tons);
        $this->assertEquals(8000000000, $result['green_sale']->total_settled_minor);
    }

    public function test_124_tailings_effluent_monitoring_and_environmental_fine_accrual(): void
    {
        $action = app(VerifyTailingsWaterQualityAndBiodiversityRehabilitationAction::class);

        // Acidic effluent breach: pH 5.2 (< 6.0) triggers environmental penalty
        $result = $action->handle(
            siteId: $this->site->id,
            samplingPoint: 'SUMP-MONITORING-01',
            waterVolumeM3: 50000.0,
            effluentPh: 5.2,
            effluentTssMgL: 45.0,
            tariffPerM3Idr: 2000
        );

        $this->assertTrue($result['threshold_exceeded']);
        $this->assertEquals(500000000, $result['water_reading']->environmental_penalty_minor);
        $this->assertEquals(100000000, $result['water_reading']->water_fee_minor);
    }

    public function test_124_reclamation_provision_lifecycle_release_upon_verified_growth(): void
    {
        $action = app(VerifyTailingsWaterQualityAndBiodiversityRehabilitationAction::class);

        // Growth NDVI 0.78 (>= 0.70) releases full provision
        $result = $action->handle(
            siteId: $this->site->id,
            effluentPh: 7.2,
            effluentTssMgL: 50.0,
            provisionAccruedIdr: 6000000000,
            growthNdvi: 0.78
        );

        $this->assertFalse($result['threshold_exceeded']);
        $this->assertEquals('VERIFIED_RELEASED', $result['reclamation']->status);
        $this->assertEquals(6000000000, $result['reclamation']->provision_released_idr);
        $this->assertTrue($result['amdal']->is_fully_approved);
    }

    public function test_125_integrated_resource_command_center_pit_to_port_margin_and_lom(): void
    {
        $action = app(CoordinateIntegratedResourceCommandCenterSlaAction::class);

        $result = $action->handle(
            siteId: $this->site->id,
            pitExtractionTon: 20000.0,
            plantProcessingTon: 18000.0,
            terminalLoadedTon: 90000.0,
            liveCommodityPriceUsd: 1920,
            realizedRevenuePerTonIdr: 2000000,
            operatingCostPerTonIdr: 1300000,
            provenReservesTon: 120000000.0,
            annualRunRateTon: 6000000.0
        );

        // Net margin = 20,000 tons * (2,000,000 - 1,300,000) = 14,000,000,000 IDR
        $this->assertEquals(14000000000, $result['net_margin_idr']);
        $this->assertEquals(20.0, $result['lom_years']);
        $this->assertEquals('MODERATE_MONITORING', $result['risk_alert']->severity);
    }

    public function test_125_risk_heatmap_triggers_critical_alert_on_covenant_breach(): void
    {
        $action = app(CoordinateIntegratedResourceCommandCenterSlaAction::class);

        // Price drop -22.5% breaches covenant threshold of -15.0%
        $result = $action->handle(
            siteId: $this->site->id,
            priceVariancePct: -22.5,
            covenantThresholdVariancePct: -15.0
        );

        $this->assertEquals('CRITICAL_COVENANT_BREACH', $result['risk_alert']->severity);
        $this->assertTrue($result['risk_alert']->c_suite_notified);
        $this->assertEquals('EXECUTE_OFFTAKE_COLLATERAL_CALL', $result['risk_alert']->remediation_action);
    }
}
