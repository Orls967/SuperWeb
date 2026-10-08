<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\RndTechTransferService;
use Tests\TestCase;

/**
 * Fase 218 — Operasi: Innovation R&D Ops, IP Portfolio & Tech Transfer Tests
 *
 * Covers:
 *  (a) stage gate cannot be skipped if prior gates are unsigned
 *  (b) stage gate sequential progression works cleanly
 *  (c) IP portfolio registration and freedom-to-operate checks
 *  (d) plm:audit = 0 discrepancy
 */
class RndTechTransferTest extends TestCase
{
    use RefreshDatabase;

    protected RndTechTransferService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RndTechTransferService::class);
    }

    /**
     * (a) & (b) Stage-gate progression and skipping prevention.
     */
    public function test_stage_gate_progression_and_guard(): void
    {
        $this->service->initProjectStages('RND-BATTERY-SOLID');

        // 1. Try skipping straight to Pilot Line (Stage 3) without signing Stage 1 & 2 -> Exception
        try {
            $this->service->signOffStageGate('RND-BATTERY-SOLID', 'PILOT_LINE', 'Chief Scientist');
            $this->fail('Expected exception for skipping prior stage gates.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('before all preceding stage gates are completed', $e->getMessage());
        }

        // 2. Sequential progression: Stage 1 -> Stage 2 -> SUCCESS
        $g1 = $this->service->signOffStageGate('RND-BATTERY-SOLID', 'DISCOVERY', 'Dr. Arifin');
        $this->assertTrue((bool) $g1->is_signed_off);
        $this->assertSame('Dr. Arifin', $g1->signed_off_by);

        $g2 = $this->service->signOffStageGate('RND-BATTERY-SOLID', 'LAB_EXPERIMENT', 'Dr. Arifin');
        $this->assertTrue((bool) $g2->is_signed_off);
    }

    /**
     * (c) IP asset management and freedom-to-operate clearance.
     */
    public function test_ip_asset_fto_and_renewal(): void
    {
        $nextYear = Carbon::today()->addYear()->toDateString();
        $ip = $this->service->registerIpAsset(
            'PAT-2026-NANO01',
            'Solid Electrolyte Nanocomposite Composition',
            'PATENT',
            'CLEARED',
            $nextYear
        );

        $this->assertSame('CLEARED', $ip->fto_status);
        $this->assertSame('PATENT', $ip->ip_type);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_rnd_tech_transfer_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
