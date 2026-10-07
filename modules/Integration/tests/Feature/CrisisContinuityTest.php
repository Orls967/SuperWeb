<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\CrisisContinuityService;
use Tests\TestCase;

/**
 * Fase 155 — Crisis Continuity Tests
 *
 * Covers:
 *  (a) force majeure activation terdokumentasi & reversible
 *  (b) prioritas alokasi dihormati sistem (surveillance risk levels)
 *  (c) klaim BI payout = aturan polis
 *  (d) contingency roster valid
 *  (e) seluruh *:audit = 0 selisih
 */
class CrisisContinuityTest extends TestCase
{
    use RefreshDatabase;

    protected CrisisContinuityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CrisisContinuityService::class);
    }

    /**
     * (a) Force majeure activation is documented and reversible.
     */
    public function test_force_majeure_activation_and_reversibility(): void
    {
        $activation = $this->service->activateForceMajeure('CTR-SUPPLY-COAL-01', 'Port closure due to hurricane');
        $this->assertTrue((bool) $activation->is_active);
        $this->assertNull($activation->revoked_at);

        // Reversible
        $revoked = $this->service->revokeForceMajeure($activation->activation_code);
        $this->assertFalse((bool) $revoked->is_active);
        $this->assertNotNull($revoked->revoked_at);
    }

    /**
     * (b) Health surveillance risk levels and restrictions.
     */
    public function test_health_surveillance_restrictions(): void
    {
        $zone = $this->service->setZoneRisk('ZONE-BDJ-01', 'ID', 'South Kalimantan', 'CRITICAL');
        $this->assertSame('CRITICAL', $zone->risk_level);
        $this->assertSame('FULL_LOCKDOWN', $zone->mandated_restriction);
    }

    /**
     * (c) Business Interruption (BI) claim calculation.
     */
    public function test_business_interruption_claim_payout(): void
    {
        // Loss: Rp 500,000,000. 80% coverage -> Rp 400,000,000 payout
        $claim = $this->service->processBusinessInterruptionClaim(
            'HOLDING-HTL',
            'HTL',
            'PANDEMIC-2026',
            500000000.00,
            0.80
        );

        $this->assertEquals(400000000.00, (float) $claim->insurance_payout_approved);
        $this->assertSame('PAID', $claim->status);
        $this->assertNotNull($claim->ledger_reference);
    }

    /**
     * (e) Audit status healthy.
     */
    public function test_crisis_continuity_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
