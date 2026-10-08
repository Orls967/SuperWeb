<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\CaseLoyaltyUnificationService;
use Tests\TestCase;

/**
 * Fase 220 — Pelanggan: Service Desk, Case Management & Loyalty Unification Tests
 *
 * Covers:
 *  (a) parent case cannot be closed while subcases remain unresolved
 *  (b) parent case closes successfully once all subcases are resolved
 *  (c) unified loyalty points earn and redemption validation
 *  (d) crm:audit = 0 discrepancy
 */
class CaseLoyaltyUnificationTest extends TestCase
{
    use RefreshDatabase;

    protected CaseLoyaltyUnificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CaseLoyaltyUnificationService::class);
    }

    /**
     * (a) & (b) Cross-line case orchestration and closure gating.
     */
    public function test_cross_line_case_orchestration_and_closure(): void
    {
        $case = $this->service->createMultiLineCase(
            'CUST-VIP-01',
            'Klaim paket wisata medis + hotel komplain',
            ['HOSPITAL', 'HOTEL']
        );
        $this->assertSame('IN_PROGRESS', $case->status);

        $subcases = DB::table('crm_cross_line_subcases')
            ->where('case_code', $case->case_code)
            ->get();
        $this->assertCount(2, $subcases);

        // 1. Try closing parent case when subcases are still open -> Exception
        try {
            $this->service->closeParentCase($case->case_code);
            $this->fail('Expected exception for premature parent case closure.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('subcases remain unresolved', $e->getMessage());
        }

        // 2. Resolve both subcases and close parent -> RESOLVED_CLOSED
        foreach ($subcases as $s) {
            $this->service->resolveSubcase($s->subcase_code);
        }

        $closed = $this->service->closeParentCase($case->case_code);
        $this->assertSame('RESOLVED_CLOSED', $closed->status);
    }

    /**
     * (c) Unified loyalty points earn and redemption.
     */
    public function test_loyalty_point_ledger(): void
    {
        // Earn 500 points from Hotel
        $tx1 = $this->service->recordLoyaltyTransaction('CUST-VIP-01', 'HOTEL', 500, 0);
        $this->assertSame(500, (int) $tx1->earned_points);

        // Redeem 200 points in Airline -> SUCCESS
        $tx2 = $this->service->recordLoyaltyTransaction('CUST-VIP-01', 'AIRLINE', 0, 200);
        $this->assertSame(200, (int) $tx2->redeemed_points);

        // Redeem 400 points when balance is only 300 -> Exception
        try {
            $this->service->recordLoyaltyTransaction('CUST-VIP-01', 'RETAIL', 0, 400);
            $this->fail('Expected exception for points redemption exceeding balance.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds balance', $e->getMessage());
        }
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_case_loyalty_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
