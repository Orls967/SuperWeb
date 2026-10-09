<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\CompensationBenefitsService;
use Tests\TestCase;

/**
 * Fase 224 — SDM: Compensation, Benefits & Total Rewards Tests
 *
 * Covers:
 *  (a) Pay structure and strict pay band enforcement
 *  (b) Edge Case 224.6: Cross-country employee transfer preserving historical compensation intact
 *  (c) Variable pay pool governance (bonus sum <= pool) & clawback mechanism
 *  (d) Edge Case 224.7: Retroactive pay adjustment requiring approval rather than payslip editing
 *  (e) Quality audit hcm:audit clean with 0 discrepancies
 */
class CompensationBenefitsTest extends TestCase
{
    use RefreshDatabase;

    protected CompensationBenefitsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CompensationBenefitsService::class);
    }

    /**
     * (a) Pay structure definition and pay band compliance (224.1 & 224.5).
     */
    public function test_pay_band_compliance(): void
    {
        // Define L3 pay structure in ID: 15M - 25M - 35M IDR
        $this->service->setPayStructure('L3', 'ID', 'IDR', 15000000, 25000000, 35000000);

        // Valid salary -> OK
        $comp = $this->service->assignCompensation('EMP-101', 'L3', 'ID', 'IDR', 22000000, '2026-01-01');
        $this->assertSame(22000000.0, (float) $comp->base_salary);

        // Salary below minimum -> Throws exception
        try {
            $this->service->assignCompensation('EMP-102', 'L3', 'ID', 'IDR', 12000000, '2026-01-01');
            $this->fail('Expected exception for salary below pay band min.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('violates pay band range', $e->getMessage());
        }

        // Salary above maximum -> Throws exception
        try {
            $this->service->assignCompensation('EMP-103', 'L3', 'ID', 'IDR', 40000000, '2026-01-01');
            $this->fail('Expected exception for salary above pay band max.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('violates pay band range', $e->getMessage());
        }
    }

    /**
     * (b) Edge Case 224.6: Cross-country transfer preserving historical records.
     */
    public function test_cross_country_transfer_history_preservation(): void
    {
        $this->service->setPayStructure('L4', 'ID', 'IDR', 30000000, 45000000, 60000000);
        $this->service->setPayStructure('L4', 'SG', 'SGD', 8000, 11000, 15000);

        // 1. Employee starts in Indonesia
        $idComp = $this->service->assignCompensation('EMP-GLOBAL-01', 'L4', 'ID', 'IDR', 40000000, '2026-01-01');
        $this->assertTrue((bool) $idComp->is_current);

        // 2. Transferred to Singapore on 2026-07-01
        $sgComp = $this->service->assignCompensation('EMP-GLOBAL-01', 'L4', 'SG', 'SGD', 12000, '2026-07-01');
        $this->assertTrue((bool) $sgComp->is_current);

        // Verify historical ID record is preserved with effective_to, not overwritten
        $oldComp = DB::table('hcm_employee_compensations')
            ->where('employee_id', 'EMP-GLOBAL-01')
            ->where('country_code', 'ID')
            ->first();

        $this->assertNotNull($oldComp);
        $this->assertFalse((bool) $oldComp->is_current);
        $this->assertSame('2026-07-01', $oldComp->effective_to);
        $this->assertSame(40000000.0, (float) $oldComp->base_salary);
    }

    /**
     * (c) Variable pay pool governance and clawback (224.2 & 224.5).
     */
    public function test_variable_pay_pool_and_clawback(): void
    {
        // Pool: 100M IDR
        $pool = $this->service->createVariablePayPool('POOL-2026-Q3', 100000000);

        // Payout 60M -> OK
        $p1 = $this->service->distributePayout($pool->pool_code, 'EMP-201', 60000000);
        $this->assertSame(60000000.0, (float) $p1->payout_amount);

        // Payout 50M (would exceed 100M total) -> Throws exception
        try {
            $this->service->distributePayout($pool->pool_code, 'EMP-202', 50000000);
            $this->fail('Expected exception for variable pay pool exhaustion.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds remaining pool budget', $e->getMessage());
        }

        // Clawback payout upon restatement
        $this->service->clawbackPayout($p1->payout_code, 'Restatement of division financial performance');
        $freshP1 = DB::table('hcm_variable_payouts')->where('payout_code', $p1->payout_code)->first();
        $this->assertTrue((bool) $freshP1->is_clawbacked);

        // Pool distributed amount decremented
        $freshPool = DB::table('hcm_variable_pay_pools')->where('pool_code', $pool->pool_code)->first();
        $this->assertSame(0.0, (float) $freshPool->distributed_amount);
    }

    /**
     * (d) Edge Case 224.7: Retroactive adjustment formal approval.
     */
    public function test_retroactive_adjustment_approval(): void
    {
        $adj = $this->service->requestRetroactiveAdjustment(
            'EMP-301',
            '2026-05',
            2500000,
            'Uncalculated shift overtime allowance'
        );
        $this->assertSame('PENDING', $adj->status);

        $this->service->approveRetroactiveAdjustment($adj->adjustment_code, 'DIR_HR_OFFICER');
        $fresh = DB::table('hcm_retroactive_adjustments')->where('adjustment_code', $adj->adjustment_code)->first();
        $this->assertSame('APPROVED', $fresh->status);
        $this->assertSame('DIR_HR_OFFICER', $fresh->approved_by);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_compensation_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
