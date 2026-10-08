<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ClimateTransitionFinanceService;
use Tests\TestCase;

class ClimateTransitionFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected ClimateTransitionFinanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ClimateTransitionFinanceService::class);
    }

    public function test_shadow_carbon_capex_appraisal_and_cash_ledger_separation(): void
    {
        // 1. Shadow carbon appraisal computes adjusted NPV without posting cash ledger (326.1 & 326.4)
        $appraisal = $this->service->conductShadowCapexAppraisal(
            appraisalCode: 'CAPEX-BOILER-CONVERSION-01',
            siteCode: 'SITE-PULP-SUMATRA',
            nominalCapexUsd: 10000000.0,
            annualCarbonTco2e: 5000.0,
            shadowPriceUsdPerTon: 80.0
        );
        // Shadow cost = 5,000 t * $80 * 5 yrs = $2,000,000. Adjusted NPV = $12,000,000
        $this->assertEquals(12000000.0, (float) $appraisal->shadow_adjusted_npv_usd);
        $this->assertFalse((bool) $appraisal->posted_to_actual_cash_ledger);
    }

    public function test_sustainability_instrument_step_up_and_anti_greenwashing_gate(): void
    {
        // 1. Issuance without evidenced direct abatement plan is rejected (326.6 Risk)
        try {
            $this->service->issueTransitionInstrument(
                instrumentCode: 'SUKUK-GREENWASH-01',
                instrumentType: 'SUSTAINABILITY_LINKED_SUKUK',
                baseCouponRatePct: 6.0,
                targetReductionPct: 20.0,
                realizedReductionPct: 15.0,
                hasEvidencedAbatementPlan: false
            );
            $this->fail('Expected exception for greenwashed instrument');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Greenwashing violation: Transition instrument issuance strictly requires verified evidence', $e->getMessage());
        }

        // 2. Met KPI: Coupon remains base rate (326.2 & 326.4)
        $achievedInstrument = $this->service->issueTransitionInstrument(
            instrumentCode: 'SUKUK-MET-TARGET-02',
            instrumentType: 'SUSTAINABILITY_LINKED_SUKUK',
            baseCouponRatePct: 5.50,
            targetReductionPct: 25.0,
            realizedReductionPct: 28.0,
            hasEvidencedAbatementPlan: true
        );
        $this->assertEquals(5.50, (float) $achievedInstrument->effective_coupon_rate_pct);
        $this->assertTrue((bool) $achievedInstrument->kpi_target_achieved);

        // 3. Missed KPI: Automatic step-up penalty applied (+0.50% -> 6.00%) (326.5 Edge Case)
        $missedInstrument = $this->service->issueTransitionInstrument(
            instrumentCode: 'SUKUK-MISSED-TARGET-03',
            instrumentType: 'SUSTAINABILITY_LINKED_SUKUK',
            baseCouponRatePct: 5.50,
            targetReductionPct: 25.0,
            realizedReductionPct: 18.0, // Missed!
            hasEvidencedAbatementPlan: true,
            stepUpPct: 0.50
        );
        $this->assertEquals(6.00, (float) $missedInstrument->effective_coupon_rate_pct);
        $this->assertFalse((bool) $missedInstrument->kpi_target_achieved);
    }

    public function test_esg_transition_finance_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->conductShadowCapexAppraisal('APP-AUD', 'SITE-AUD', 1000.0, 10.0, 50.0);
        $this->service->issueTransitionInstrument('INS-AUD', 'GREEN_LOAN', 5.0, 10.0, 15.0, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: shadow cost illegally posted to cash ledger
        DB::table('internal_carbon_shadow_capex_appraisals')->insert([
            'appraisal_code' => 'APP-DEFECT-POSTED-CASH',
            'site_code' => 'SITE-AUD',
            'nominal_capex_usd' => 5000.0,
            'annual_carbon_intensity_tco2e' => 10.0,
            'internal_carbon_shadow_price_usd_per_ton' => 50.0,
            'shadow_adjusted_npv_usd' => 7500.0,
            'posted_to_actual_cash_ledger' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
