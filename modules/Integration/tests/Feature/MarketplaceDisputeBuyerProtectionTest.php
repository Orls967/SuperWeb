<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\MarketplaceDisputeBuyerProtectionService;
use Tests\TestCase;

class MarketplaceDisputeBuyerProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected MarketplaceDisputeBuyerProtectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MarketplaceDisputeBuyerProtectionService::class);
    }

    public function test_dispute_evidence_and_conflicted_reviewer_replacement_edge_case(): void
    {
        // 1. Filing without evidence fails (373.1 & 373.4)
        try {
            $this->service->fileDisputeCase(
                disputeCode: 'DISP-PARTS-001',
                buyerId: 'BUYER-FLEET-LOGISTICS',
                sellerId: 'SELLER-SPARE-PARTS',
                assignedReviewerId: 'REV-USER-ALICE',
                hasEvidence: false // Missing evidence!
            );
            $this->fail('Expected exception for dispute filed without evidence');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Required evidence checklist incomplete', $e->getMessage());
        }

        // 2. Conflicted reviewer without alternate fails (373.4 & 373.5 Edge Case)
        try {
            $this->service->fileDisputeCase(
                disputeCode: 'DISP-PARTS-002',
                buyerId: 'BUYER-FLEET-LOGISTICS',
                sellerId: 'SELLER-SPARE-PARTS',
                assignedReviewerId: 'REV-USER-ALICE',
                hasEvidence: true,
                isConflicted: true,
                alternateReviewerId: null // No alternate!
            );
            $this->fail('Expected exception for conflicted reviewer without alternate');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Neutral alternate reviewer must be assigned', $e->getMessage());
        }

        // 3. Conflicted reviewer automatically replaced with neutral alternate succeeds (373.5)
        $case = $this->service->fileDisputeCase(
            disputeCode: 'DISP-PARTS-003',
            buyerId: 'BUYER-FLEET-LOGISTICS',
            sellerId: 'SELLER-SPARE-PARTS',
            assignedReviewerId: 'REV-USER-ALICE',
            hasEvidence: true,
            isConflicted: true,
            alternateReviewerId: 'REV-USER-NEUTRAL-BOB'
        );
        $this->assertEquals('REV-USER-NEUTRAL-BOB', $case->assigned_reviewer_id);
        $this->assertTrue((bool) $case->reviewer_conflict_of_interest);
    }

    public function test_buyer_protection_hold_released_only_after_adjudication(): void
    {
        // 1. Releasing hold before adjudication fails (373.2 & 373.4)
        try {
            $this->service->releaseProtectionHold(
                holdCode: 'HOLD-ESCROW-001',
                disputeCode: 'DISP-PARTS-003',
                holdAmountUsd: 2500.00,
                outcomeAdjudicated: false // Unadjudicated!
            );
            $this->fail('Expected exception for premature hold release');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Hold release permitted only after formal dispute adjudication', $e->getMessage());
        }

        // 2. Releasing hold after adjudication succeeds (373.2 & 373.4)
        $released = $this->service->releaseProtectionHold(
            holdCode: 'HOLD-ESCROW-002',
            disputeCode: 'DISP-PARTS-003',
            holdAmountUsd: 2500.00,
            outcomeAdjudicated: true
        );
        $this->assertTrue((bool) $released->outcome_adjudicated);
        $this->assertTrue((bool) $released->hold_released);
    }

    public function test_marketplace_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->fileDisputeCase('D-AUD', 'B1', 'S1', 'REV1', true, false, null);
        $this->service->releaseProtectionHold('H-AUD', 'D-AUD', 100.00, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unadjudicated hold released
        DB::table('marketplace_buyer_protection_holds')->insert([
            'hold_code' => 'H-DEFECT-PREMATURE',
            'dispute_code' => 'D-AUD',
            'hold_amount_usd' => 500.00,
            'outcome_adjudicated' => false, // Discrepancy!
            'hold_released' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
