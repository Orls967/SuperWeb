<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ClimateRiskInsuranceResilienceService;
use Tests\TestCase;

class ClimateRiskInsuranceResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected ClimateRiskInsuranceResilienceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ClimateRiskInsuranceResilienceService::class);
    }

    public function test_adaptation_measure_premium_credit_verification_guard(): void
    {
        // 1. Policy with verified adaptation measure gets premium credit (329.1 & 329.4)
        $policy = $this->service->issueResiliencePolicy(
            policyCode: 'POL-COASTAL-SMELTER-01',
            siteCode: 'SITE-PORT-MOROWALI',
            basePremiumUsd: 500000.0,
            verifiedAdaptationMeasure: true,
            requestedMitigationCreditUsd: 75000.0
        );
        $this->assertEquals(75000.0, (float) $policy->risk_mitigation_credit_usd);
        $this->assertEquals(425000.0, (float) $policy->final_billed_premium_usd);

        // 2. Policy without verified adaptation requesting credit is rejected (329.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Actuarial audit breach: Risk mitigation premium credit requires verified adaptation');
        $this->service->issueResiliencePolicy(
            policyCode: 'POL-UNVERIFIED-CREDIT',
            siteCode: 'SITE-MINE-PIT',
            basePremiumUsd: 300000.0,
            verifiedAdaptationMeasure: false,
            requestedMitigationCreditUsd: 50000.0
        );
    }

    public function test_parametric_sensor_outage_fallback_and_payout_gate(): void
    {
        // 1. Online sensor reaching threshold triggers payout (329.3 & 329.4)
        $onlineEvent = $this->service->evaluateParametricTrigger(
            eventCode: 'EV-CYCLONE-CAT3-01',
            siteCode: 'SITE-PORT-MOROWALI',
            sensorStatus: 'ONLINE',
            sensorValue: 125.0, // > 100 threshold
            payoutThreshold: 100.0
        );
        $this->assertTrue((bool) $onlineEvent->parametric_payout_triggered);

        // 2. Sensor outage without manual fallback protocol throws exception (329.5 Edge Case)
        try {
            $this->service->evaluateParametricTrigger(
                eventCode: 'EV-OUTAGE-DISASTER-02',
                siteCode: 'SITE-PORT-MOROWALI',
                sensorStatus: 'OUTAGE_OFFLINE',
                sensorValue: null,
                manualFallbackActivated: false,
                hasOfficialMeteoData: false
            );
            $this->fail('Expected exception for unverified sensor outage claim');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Sensor outage requires manual fallback protocol with official meteorological data', $e->getMessage());
        }

        // 3. Sensor outage with manual fallback and official meteo station data succeeds (329.5)
        $fallbackEvent = $this->service->evaluateParametricTrigger(
            eventCode: 'EV-OUTAGE-DISASTER-03',
            siteCode: 'SITE-PORT-MOROWALI',
            sensorStatus: 'OUTAGE_OFFLINE',
            sensorValue: 110.0,
            manualFallbackActivated: true,
            hasOfficialMeteoData: true,
            payoutThreshold: 100.0
        );
        $this->assertTrue((bool) $fallbackEvent->parametric_payout_triggered);
        $this->assertTrue((bool) $fallbackEvent->is_manual_fallback_activated);
    }

    public function test_ins_esg_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->issueResiliencePolicy('POL-AUD', 'S1', 1000.0, true, 100.0);
        $this->service->evaluateParametricTrigger('EV-AUD', 'S1', 'ONLINE', 50.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: premium credit without verified adaptation
        DB::table('climate_insurance_resilience_policies')->insert([
            'policy_code' => 'POL-DEFECT-UNVERIFIED-CREDIT',
            'site_code' => 'S1',
            'base_premium_usd' => 1000.0,
            'has_verified_adaptation_measure' => false, // Discrepancy!
            'risk_mitigation_credit_usd' => 200.0,
            'final_billed_premium_usd' => 800.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
