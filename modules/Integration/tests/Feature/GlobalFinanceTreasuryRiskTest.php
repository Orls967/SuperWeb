<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\GlobalFinanceTreasuryRiskService;
use Tests\TestCase;

class GlobalFinanceTreasuryRiskTest extends TestCase
{
    use RefreshDatabase;

    protected GlobalFinanceTreasuryRiskService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GlobalFinanceTreasuryRiskService::class);
    }

    public function test_exposure_limit_board_approval_edge_case(): void
    {
        // 1. Exposure exceeding limit without Board approval fails (376.2, 376.4, 376.5 Edge Case)
        try {
            $this->service->evaluateCounterpartyExposure(
                exposureCode: 'EXP-CORP-BANK-001',
                counterpartyName: 'Global Commodities Broker',
                grossExposureUsd: 12000000.00,
                eligibleCollateralUsd: 2000000.00, // Net 10,000,000
                exposureLimitUsd: 8000000.00, // Limit 8,000,000
                boardApprovalGranted: false // No Board approval!
            );
            $this->fail('Expected exception for exceeding exposure limit without Board approval');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds counterparty limit ($8000000) and requires Board approval', $e->getMessage());
        }

        // 2. Exposure exceeding limit with Board approval succeeds (376.5)
        $approved = $this->service->evaluateCounterpartyExposure(
            exposureCode: 'EXP-CORP-BANK-002',
            counterpartyName: 'Global Commodities Broker',
            grossExposureUsd: 12000000.00,
            eligibleCollateralUsd: 2000000.00,
            exposureLimitUsd: 8000000.00,
            boardApprovalGranted: true // Board approved!
        );
        $this->assertTrue((bool) $approved->exposure_limit_exceeded);
        $this->assertTrue((bool) $approved->board_approval_granted);
        $this->assertTrue((bool) $approved->transaction_proceeded);
    }

    public function test_funding_waterfall_never_overdraws(): void
    {
        // 1. Funding request exceeding available liquidity fails (376.3 & 376.4)
        try {
            $this->service->executeFundingWaterfall(
                waterfallCode: 'WTF-STRESS-001',
                requestedAmountUsd: 25000000.00,
                cashPoolAvailableUsd: 10000000.00,
                committedFacilitiesAvailableUsd: 10000000.00 // Total 20,000,000 < 25,000,000!
            );
            $this->fail('Expected exception for overdrawing funding waterfall');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds total group available liquidity', $e->getMessage());
        }

        // 2. Funding request within limits executes safely (376.3 & 376.4)
        $waterfall = $this->service->executeFundingWaterfall(
            waterfallCode: 'WTF-STRESS-002',
            requestedAmountUsd: 15000000.00,
            cashPoolAvailableUsd: 10000000.00,
            committedFacilitiesAvailableUsd: 10000000.00
        );
        $this->assertEquals(15000000.00, $waterfall->total_drawn_usd);
        $this->assertTrue((bool) $waterfall->never_overdrawn);
    }

    public function test_group_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->evaluateCounterpartyExposure('E-AUD', 'BANK', 5000000.00, 0.00, 10000000.00, false);
        $this->service->executeFundingWaterfall('W-AUD', 1000000.00, 2000000.00, 2000000.00);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: over-limit transaction proceeded without Board approval
        DB::table('global_treasury_counterparty_exposures')->insert([
            'exposure_code' => 'E-DEFECT-UNAPPROVED',
            'counterparty_name' => 'BANK',
            'gross_exposure_usd' => 20000000.00,
            'eligible_collateral_usd' => 0.00,
            'net_exposure_usd' => 20000000.00,
            'exposure_limit_usd' => 5000000.00,
            'exposure_limit_exceeded' => true,
            'board_approval_granted' => false, // Discrepancy!
            'transaction_proceeded' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
