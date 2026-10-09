<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\EnterpriseRiskAppetiteBoardReportingService;
use Tests\TestCase;

class EnterpriseRiskAppetiteBoardReportingTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseRiskAppetiteBoardReportingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseRiskAppetiteBoardReportingService::class);
    }

    public function test_kri_appetite_monitoring_and_board_risk_pack_flow(): void
    {
        // 450.1 Register operational KRI: Unplanned Data Center Downtime Hours (Appetite: max 2.0 hrs/year)
        $kri = $this->service->registerKri(
            kriCode: 'KRI-OPS-DC-DOWNTIME',
            category: 'operational',
            appetiteLimit: 2.00,
            currentValue: 0.50,
            owner: 'VP Infrastructure & Cloud Engineering'
        );

        $this->assertEquals('KRI-OPS-DC-DOWNTIME', $kri->kri_code);
        $this->assertFalse((bool) $kri->is_in_breach);

        // 450.3 Generate Board Risk Pack
        $pack = $this->service->generateBoardRiskPack();
        $this->assertEquals('WITHIN_APPETITE', $pack['status']);
        $this->assertEquals(0, $pack['breached_kris']);

        // 450.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_recurrent_kri_breach_escalates_to_board_committee_edge_case(): void
    {
        // 450.1 & 450.2 Credit risk KRI: Gross NPL Ratio (Appetite: max 2.50%)
        $kri = $this->service->registerKri(
            kriCode: 'KRI-CREDIT-NPL-FINANCE',
            category: 'credit',
            appetiteLimit: 2.50,
            currentValue: 2.10,
            owner: 'Chief Risk Officer'
        );

        // First breach: 2.80% (> 2.50%)
        $firstBreach = $this->service->updateKriReading('KRI-CREDIT-NPL-FINANCE', 2.80);
        $this->assertTrue((bool) $firstBreach->is_in_breach);
        $this->assertEquals(1, $firstBreach->breach_recurrence_count);
        $this->assertNotNull($firstBreach->remediation_due_date);

        // 450.5 Edge case: Repeated second breach (spikes to 3.20%) forces mandatory board escalation
        $secondBreach = $this->service->updateKriReading('KRI-CREDIT-NPL-FINANCE', 3.20);
        $this->assertTrue((bool) $secondBreach->is_in_breach);
        $this->assertEquals(2, $secondBreach->breach_recurrence_count);
        $this->assertTrue((bool) $secondBreach->escalated_to_board_risk_committee);

        // Board risk pack flags breach and escalation
        $pack = $this->service->generateBoardRiskPack();
        $this->assertEquals('BREACH_ESCALATED', $pack['status']);
        $this->assertEquals(1, $pack['board_escalated_count']);

        // Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }
}
