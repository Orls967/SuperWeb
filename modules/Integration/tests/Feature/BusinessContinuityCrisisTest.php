<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\BusinessContinuityCrisisService;
use Tests\TestCase;

/**
 * Fase 206 — Risiko: Business Continuity 30 Lini & Crisis Command Tests
 *
 * Covers:
 *  (a) BIA tiering establishes RTO targets
 *  (b) drill execution measures RTO against target SLA
 *  (c) crisis war room activation requires approved holding statement
 *  (d) crisis:audit = 0 discrepancy
 */
class BusinessContinuityCrisisTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessContinuityCrisisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BusinessContinuityCrisisService::class);
    }

    /**
     * (a) & (b) BIA continuity plans and drill verification.
     */
    public function test_bia_continuity_drill_rto(): void
    {
        // Tier 1 Life Safety target RTO = 15 minutes
        $plan = $this->service->registerContinuityPlan('L26_AVIATION', 'TIER_1_LIFE_SAFETY', 15);
        $this->assertSame(15, (int) $plan->target_rto_minutes);

        // 1. Drill recovers within 10 minutes (<= 15) -> SUCCESS
        $drillPass = $this->service->recordDrillResult($plan->plan_code, 10);
        $this->assertTrue((bool) $drillPass->rto_met);
        $this->assertSame(10, (int) $drillPass->actual_drill_rto_minutes);

        // 2. Drill recovers in 25 minutes (> 15) -> Fails RTO
        $drillFail = $this->service->recordDrillResult($plan->plan_code, 25);
        $this->assertFalse((bool) $drillFail->rto_met);
    }

    /**
     * (c) Crisis war room holding statement approval.
     */
    public function test_crisis_war_room_statement_approval(): void
    {
        // 1. Unapproved statement -> Exception
        try {
            $this->service->activateWarRoom('DATA_CENTER_FLOODING', 'Sistem sedang dialihkan ke DR site.', '   ');
            $this->fail('Expected exception for unapproved statement.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Holding statement requires approval', $e->getMessage());
        }

        // 2. Approved statement -> SUCCESS
        $room = $this->service->activateWarRoom('DATA_CENTER_FLOODING', 'Sistem sedang dialihkan ke DR site.', 'VP Corporate Communications');
        $this->assertSame('ACTIVE', $room->status);
        $this->assertSame('VP Corporate Communications', $room->statement_approved_by);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies (when RTO met).
     */
    public function test_business_continuity_audit(): void
    {
        // Reset and set clean meeting RTO
        DB::table('erm_bia_continuity_plans')->truncate();
        $plan = $this->service->registerContinuityPlan('L26_AVIATION', 'TIER_1_LIFE_SAFETY', 15);
        $this->service->recordDrillResult($plan->plan_code, 12);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
