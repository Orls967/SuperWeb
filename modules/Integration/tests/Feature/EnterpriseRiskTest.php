<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\EnterpriseRiskService;
use Tests\TestCase;

/**
 * Fase 202 — Risiko: Enterprise Risk Management Framework Tests
 *
 * Covers:
 *  (a) risk register calculates inherent risk score accurately
 *  (b) KRI monitoring detects risk appetite threshold breach
 *  (c) appetite breach triggers mandatory escalation to board
 *  (d) risk:audit = 0 discrepancy
 */
class EnterpriseRiskTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseRiskService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseRiskService::class);
    }

    /**
     * (a) Risk register inherent and residual scoring.
     */
    public function test_risk_register_inherent_scoring(): void
    {
        // Likelihood 4, Impact 5 -> Inherent 20
        $risk = $this->service->registerRisk('L26_AVIATION', 'OPERATIONAL', 'Aircraft Engine Failure', 4, 5, 'MITIGATE', 4);
        $this->assertSame(20, (int) $risk->inherent_risk_score);
        $this->assertSame(4, (int) $risk->residual_risk_score);
        $this->assertSame('MITIGATE', $risk->treatment_strategy);
    }

    /**
     * (b) & (c) KRI monitoring and Board escalation.
     */
    public function test_kri_monitoring_and_board_escalation(): void
    {
        // 1. Within appetite: NPF ratio 2.5% <= 5.0% -> NORMAL
        $kri1 = $this->service->monitorKri('L05_SYARIAH_BANKING', 'NPF_RATIO', 5.0, 2.5);
        $this->assertFalse((bool) $kri1->appetite_breached);
        $this->assertSame('NORMAL', $kri1->board_escalation_status);

        // 2. Breached appetite: NPF ratio 6.8% > 5.0% -> ESCALATED_TO_BOARD
        $kri2 = $this->service->monitorKri('L05_SYARIAH_BANKING', 'NPF_RATIO', 5.0, 6.8);
        $this->assertTrue((bool) $kri2->appetite_breached);
        $this->assertSame('ESCALATED_TO_BOARD', $kri2->board_escalation_status);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_enterprise_risk_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
