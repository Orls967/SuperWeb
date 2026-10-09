<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SustainableFinanceReportingService;
use Tests\TestCase;

class SustainableFinanceReportingTest extends TestCase
{
    use RefreshDatabase;

    protected SustainableFinanceReportingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SustainableFinanceReportingService::class);
    }

    public function test_green_instrument_proceeds_allocation_and_margin_adjustment(): void
    {
        // 448.1 Register 500 Billion IDR Green Sukuk facility
        $inst = $this->service->registerInstrument(
            instrumentCode: 'SUKUK-GREEN-2026-I',
            type: 'green_sukuk',
            facilityAmount: 500000000000.00,
            baseMarginPercent: 6.25,
            reviewerVerified: true
        );

        $this->assertEquals('SUKUK-GREEN-2026-I', $inst->instrument_code);
        $this->assertTrue((bool) $inst->reviewer_independence_verified);

        // 448.2 & 448.4 Allocate proceeds to eligible solar farm project
        $alloc = $this->service->allocateProceeds(
            allocationCode: 'ALC-SOLAR-01',
            instrumentCode: 'SUKUK-GREEN-2026-I',
            projectCode: 'PRJ-SOLAR-CIRATA',
            amount: 250000000000.00,
            isEligible: true
        );

        $this->assertEquals(250000000000.00, (float) $alloc->allocated_amount);

        // 448.1 & 448.4 Adjust margin based on verified KPI score (92 -> -25 bps)
        $adj = $this->service->adjustSustainabilityMargin('SUKUK-GREEN-2026-I', 92.00, true);
        $this->assertEquals(-25.00, (float) $adj->current_margin_adjustment_bps);

        // 448.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_diversion_to_ineligible_project_and_self_reported_kpi_blocked_edge_cases(): void
    {
        $this->service->registerInstrument('LOAN-SLL-01', 'sustainability_linked', 100000000000.00, 7.50, true);

        // 448.5 Edge case: Diversion of green proceeds to ineligible project is strictly blocked
        try {
            $this->service->allocateProceeds(
                allocationCode: 'ALC-DIRTY-01',
                instrumentCode: 'LOAN-SLL-01',
                projectCode: 'PRJ-COAL-EXPANSION',
                amount: 50000000000.00,
                isEligible: false // Ineligible!
            );
            $this->fail('Expected exception for diverted ineligible project proceeds');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Proceeds diversion detected! Project \'PRJ-COAL-EXPANSION\' is not certified', $e->getMessage());
        }

        // 448.6 Risk: Margin adjustment using unauthoritative self-reported data is blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('KPI score must originate from an authoritative external verifier');

        $this->service->adjustSustainabilityMargin('LOAN-SLL-01', 95.00, false);
    }
}
