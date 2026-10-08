<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ClimateAdaptationResilienceService;
use Tests\TestCase;

class ClimateAdaptationResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected ClimateAdaptationResilienceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ClimateAdaptationResilienceService::class);
    }

    public function test_site_climate_exposure_and_sensor_fallback_uncertainty_label(): void
    {
        // 1. Normal site with verified sensor (287.1 & 287.4)
        $normalSite = $this->service->recordSiteClimateExposure(
            siteCode: 'SITE-PORT-SURABAYA',
            lat: -7.2575,
            lng: 112.7521,
            hazardType: 'FLOOD',
            riskScore: 7.5,
            potentialLossUsd: 15000000.0,
            sensorOnline: true
        );
        $this->assertEquals('VERIFIED_SENSOR', $normalSite->weather_data_quality_label);

        // 2. Sensor outage falls back to official data and explicitly labels uncertainty (287.5 Edge Case)
        $outageSite = $this->service->recordSiteClimateExposure(
            siteCode: 'SITE-HALMAHERA-REMOTE',
            lat: 0.5897,
            lng: 127.8643,
            hazardType: 'HEAT_STRESS',
            riskScore: 6.2,
            potentialLossUsd: 8000000.0,
            sensorOnline: false // Sensor down!
        );
        $this->assertEquals('OFFICIAL_FALLBACK_UNCERTAIN', $outageSite->weather_data_quality_label);
    }

    public function test_adaptation_measure_roi_and_insurance_credit_pre_requisite(): void
    {
        // 1. Incomplete adaptation measure: capex $1,000,000; avoided loss $3,000,000 => ROI 200% (287.2 & 287.4)
        $measure = $this->service->registerAdaptationMeasure(
            measureCode: 'EPC-FLOOD-WALL-01',
            siteCode: 'SITE-PORT-SURABAYA',
            measureName: 'Seawall and Flood Gate Installation',
            capexCostUsd: 1000000.0,
            avoidedLossUsd: 3000000.0,
            isCompleted: false
        );
        $this->assertEquals(200.0, (float) $measure->adaptation_roi_pct);
        $this->assertFalse((bool) $measure->insurance_resilience_credit_eligible);

        // 2. Attempting to claim insurance discount before measure completion is blocked (287.7 Edge Case)
        try {
            $this->service->claimInsuranceResilienceCredit('EPC-FLOOD-WALL-01');
            $this->fail('Expected exception for uncompleted adaptation measure credit claim');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must be fully completed and verified prior to insurance resilience credit', $e->getMessage());
        }

        // 3. Completed measure successfully receives resilience credit (287.7)
        $completedMeasure = $this->service->registerAdaptationMeasure(
            measureCode: 'EPC-FLOOD-WALL-02',
            siteCode: 'SITE-PORT-SURABAYA',
            measureName: 'Backup Power Elevation',
            capexCostUsd: 500000.0,
            avoidedLossUsd: 1200000.0,
            isCompleted: true
        );
        $claimed = $this->service->claimInsuranceResilienceCredit('EPC-FLOOD-WALL-02');
        $this->assertTrue((bool) $claimed->insurance_resilience_credit_eligible);
    }

    public function test_supply_chain_climate_vulnerability_triggers_alternative_routing(): void
    {
        // 1. Low risk supplier (score 4.0) -> no mitigation trigger (287.3)
        $safeSupplier = $this->service->evaluateSupplierClimateRisk('SUPPLIER-LOCAL-JAVA', 'JAVA', 4.0);
        $this->assertFalse((bool) $safeSupplier->mitigation_plan_triggered);
        $this->assertNull($safeSupplier->alternative_routing_code);

        // 2. High risk supplier (score 8.5 >= 7.0) -> triggers mitigation & alternative routing (287.3 & 287.4)
        $riskySupplier = $this->service->evaluateSupplierClimateRisk('SUPPLIER-DROUGHT-ZONE', 'NUSA_TENGGARA', 8.5);
        $this->assertTrue((bool) $riskySupplier->mitigation_plan_triggered);
        $this->assertEquals('ROUTE-BYPASS-CLIMATE-NUSA_TENGGARA', $riskySupplier->alternative_routing_code);
    }

    public function test_climate_resilience_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->recordSiteClimateExposure('SITE-AUD', 0.0, 0.0, 'FLOOD', 5.0, 1000.0, true);
        $this->service->registerAdaptationMeasure('EPC-AUD', 'SITE-AUD', 'BARRIER', 100.0, 300.0, true);
        $this->service->claimInsuranceResilienceCredit('EPC-AUD');
        $this->service->evaluateSupplierClimateRisk('SUP-AUD', 'REGION', 8.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: incomplete measure with insurance credit granted
        DB::table('esg_adaptation_measures')->insert([
            'measure_code' => 'EPC-BREACH-UNCOMPLETED',
            'asset_site_code' => 'SITE-AUD',
            'measure_name' => 'Wall',
            'capex_cost_usd' => 100.0,
            'avoided_loss_benefit_usd' => 200.0,
            'adaptation_roi_pct' => 100.0,
            'measure_completed' => false, // Discrepancy: not completed!
            'insurance_resilience_credit_eligible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
