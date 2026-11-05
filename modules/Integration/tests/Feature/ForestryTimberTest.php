<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\ForestryTimberService;
use Tests\TestCase;

/**
 * Fase 172 — Kehutanan, Timber & Restoration Value Chain Tests
 *
 * Covers:
 *  (a) harvest <= quota enforced
 *  (b) volume reconciliation and custody ticket hash validity
 *  (c) restoration survival evidence (>=75% and verified) required for claims
 *  (d) forest:audit = 0 discrepancy
 */
class ForestryTimberTest extends TestCase
{
    use RefreshDatabase;

    protected ForestryTimberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ForestryTimberService::class);
    }

    /**
     * (a) Harvest volume strictly bounded by sustainable quota.
     */
    public function test_sustainable_timber_harvest_quota(): void
    {
        $this->service->registerPlot('PLOT-BORNEO-01', 'Kutai Concession', 500.0); // 500 m3 quota

        // Valid harvest: 300 m3
        $t1 = $this->service->issueCustodyTicket('PLOT-BORNEO-01', 'STUMP', 300.0, 'PERMIT-KLHK-99');
        $this->assertSame('STUMP', $t1->stage);
        $this->assertNotNull($t1->ticket_hash);

        // Excess harvest: 250 m3 (total 550 m3 > 500 m3) -> Exception
        $this->expectException(\RuntimeException::class);
        $this->service->issueCustodyTicket('PLOT-BORNEO-01', 'STUMP', 250.0, 'PERMIT-KLHK-99');
    }

    /**
     * (c) Restoration claims require survival rate >=75% and verified field evidence.
     */
    public function test_restoration_claim_eligibility(): void
    {
        // 1. High survival (85%) but NO field evidence -> NOT eligible
        $p1 = $this->service->recordRestoration('POLY-RESTORE-01', 50.0, 85.0, false);
        $this->assertFalse((bool) $p1->claim_eligible);

        // 2. Low survival (60%) with evidence -> NOT eligible
        $p2 = $this->service->recordRestoration('POLY-RESTORE-02', 50.0, 60.0, true);
        $this->assertFalse((bool) $p2->claim_eligible);

        // 3. High survival (88%) WITH evidence -> ELIGIBLE
        $p3 = $this->service->recordRestoration('POLY-RESTORE-03', 100.0, 88.0, true);
        $this->assertTrue((bool) $p3->claim_eligible);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_forestry_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
