<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\ProfessionalServicesService;
use Tests\TestCase;

/**
 * Fase 175 — Professional Services & Project Marketplace Tests
 *
 * Covers:
 *  (a) sealed proposals hidden until formal opening event
 *  (b) milestone cannot pay before deliverable acceptance
 *  (c) consultant access expires when validity date passes
 *  (d) psv:audit = 0 discrepancy
 */
class ProfessionalServicesTest extends TestCase
{
    use RefreshDatabase;

    protected ProfessionalServicesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProfessionalServicesService::class);
    }

    /**
     * (a) Sealed proposals are concealed until unsealing.
     */
    public function test_sealed_proposals_hidden_until_opening(): void
    {
        $proposal = $this->service->submitSealedProposal('RFP-CLOUD-MIGRATE', 'FIRM-MCKINSEY-ID', 1500000000.0);

        // Public/competitor cannot see bid
        $hiddenBid = $this->service->viewProposalBid($proposal->proposal_code, false);
        $this->assertNull($hiddenBid);

        // Procurement admin can see
        $adminBid = $this->service->viewProposalBid($proposal->proposal_code, true);
        $this->assertEquals(1500000000.00, $adminBid);

        // Once RFP unsealed -> Public can see
        $this->service->openProposals('RFP-CLOUD-MIGRATE');
        $unsealedBid = $this->service->viewProposalBid($proposal->proposal_code, false);
        $this->assertEquals(1500000000.00, $unsealedBid);
    }

    /**
     * (b) Milestone payment blocked until deliverable is accepted.
     */
    public function test_milestone_payment_requires_acceptance(): void
    {
        $milestone = $this->service->createMilestone('SOW-FINTECH-01', 'Architecture Blueprints', 250000000.0);

        // Pay before acceptance -> Exception
        try {
            $this->service->payMilestone($milestone->milestone_code);
            $this->fail('Expected exception when paying unaccepted milestone.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('has not been officially accepted', $e->getMessage());
        }

        // Accept deliverable with document checksum -> Pay SUCCESS
        $this->service->acceptDeliverable($milestone->milestone_code, 'sha256:abcd1234efgh5678');
        $paid = $this->service->payMilestone($milestone->milestone_code);
        $this->assertTrue((bool) $paid->is_paid);
    }

    /**
     * (c) Consultant access grant expiration check.
     */
    public function test_consultant_access_expiration(): void
    {
        // 1. Valid grant expiring in 30 days -> ACTIVE
        $activeGrant = $this->service->grantAccess(4001, 'SOW-AUDIT', Carbon::now()->addDays(30));
        $this->assertTrue($this->service->verifyAccessActive($activeGrant->grant_code));

        // 2. Expired grant from 5 days ago -> INACTIVE
        $expiredGrant = $this->service->grantAccess(4002, 'SOW-AUDIT', Carbon::now()->subDays(5));
        $this->assertFalse($this->service->verifyAccessActive($expiredGrant->grant_code));
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_professional_services_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
