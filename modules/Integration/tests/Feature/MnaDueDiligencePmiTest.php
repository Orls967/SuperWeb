<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\MnaDueDiligencePmiService;
use Tests\TestCase;

class MnaDueDiligencePmiTest extends TestCase
{
    use RefreshDatabase;

    protected MnaDueDiligencePmiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MnaDueDiligencePmiService::class);
    }

    public function test_mna_deal_valuation_adjustment_and_synergy_tracking_flow(): void
    {
        // 453.1 Register acquisition target deal (Initial 120B IDR)
        $deal = $this->service->registerDeal(
            dealCode: 'DEAL-ACQ-LOGISTICS-CO',
            targetName: 'PT Nusantara Fast Freight',
            initialValuation: 120000000000.00
        );

        $this->assertEquals('DEAL-ACQ-LOGISTICS-CO', $deal->deal_code);

        // 453.1 & 453.4 DD workstream finds tax exposure & software license deficit -> adjust valuation by 15B
        $adjusted = $this->service->adjustValuationFromDdFindings('DEAL-ACQ-LOGISTICS-CO', 15000000000.00);
        $this->assertEquals(105000000000.00, (float) $adjusted->final_adjusted_valuation);
        $this->assertTrue((bool) $adjusted->dd_workstreams_cleared);

        // 453.2 & 453.3 Register PMI cost synergy tracker
        $syn = $this->service->registerSynergyTracker(
            synergyCode: 'SYN-FLEET-RATIONALIZATION',
            dealCode: 'DEAL-ACQ-LOGISTICS-CO',
            synergyType: 'cost_synergy',
            targetAmount: 20000000000.00
        );

        $this->assertEquals('SYN-FLEET-RATIONALIZATION', $syn->synergy_code);

        // 453.3 & 453.4 Record realized synergy (achieved 18B -> 90% of target, healthy)
        $realized = $this->service->recordRealizedSynergy('SYN-FLEET-RATIONALIZATION', 18000000000.00);
        $this->assertEquals(18000000000.00, (float) $realized->realized_synergy_amount);
        $this->assertFalse((bool) $realized->underperformance_evaluated);

        // 453.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_underperforming_synergy_evaluation_and_excessive_valuation_cut_edge_cases(): void
    {
        // 453.5 Edge case: Underperforming synergy (< 70% of target) triggers evaluation
        $this->service->registerSynergyTracker('SYN-CROSS-SELL', 'DEAL-X', 'revenue_synergy', 10000000000.00);

        // Realized only 4B (< 7B)
        $lagging = $this->service->recordRealizedSynergy('SYN-CROSS-SELL', 4000000000.00);
        $this->assertTrue((bool) $lagging->underperformance_evaluated);

        // Valuation cut greater than initial valuation is blocked
        $this->service->registerDeal('DEAL-FAIL', 'Failing Startup', 5000000000.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Resulting valuation is zero or negative');

        $this->service->adjustValuationFromDdFindings('DEAL-FAIL', 6000000000.00); // 6B > 5B!
    }
}
