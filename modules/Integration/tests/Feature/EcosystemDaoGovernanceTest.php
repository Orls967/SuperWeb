<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\EcosystemDaoGovernanceService;
use Tests\TestCase;

/**
 * Fase 233 — Tata Kelola: Ecosystem Governance, DAO Evolution & Stakeholder Voting Tests
 *
 * Covers:
 *  (a) Proposal classes enforcing distinct quorum & supermajority thresholds
 *  (b) Liquid democracy vote delegation correctly augmenting delegate voting weight
 *  (c) Edge Case 233.6: In-flight proposal cancellation logged and excluded from tally results
 *  (d) Execution requirement for valid passed result and mandatory safety review clearance
 *  (e) Quality audit governance:audit clean with 0 discrepancies
 */
class EcosystemDaoGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected EcosystemDaoGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EcosystemDaoGovernanceService::class);
    }

    /**
     * (a) & (b) Proposal classes, liquid democracy delegation, and hash chaining (233.1 & 233.3).
     */
    public function test_proposal_classes_and_liquid_democracy_delegation(): void
    {
        // 1. Create Strategic proposal (60% quorum, 66.67% supermajority)
        $prop = $this->service->createProposal('STRATEGIC', 'Acquire Renewable Energy Infrastructure Port', true);
        $this->assertSame('STRATEGIC', $prop->proposal_class);
        $this->assertEquals(60.0, (float) $prop->quorum_pct);
        $this->assertEquals(66.67, (float) $prop->supermajority_threshold_pct);

        // 2. Stakeholder B delegates 2.0 voting weight to Stakeholder A
        $this->service->delegateVote('VOTER-B', 'VOTER-A', 2.0, 'STRATEGIC');

        // 3. Stakeholder A casts YES vote with base weight 1.0 (effective weight becomes 1.0 + 2.0 = 3.0)
        $vote1 = $this->service->castVote($prop->proposal_code, 'VOTER-A', 'YES', 1.0);
        $this->assertEquals(3.0, (float) $vote1->effective_weight);
        $this->assertNotEmpty($vote1->vote_hash);

        // 4. Stakeholder C casts NO vote with base weight 1.0
        $vote2 = $this->service->castVote($prop->proposal_code, 'VOTER-C', 'NO', 1.0);
        $this->assertSame($vote1->vote_hash, $vote2->prev_hash); // Hash chain intact
    }

    /**
     * (c) Edge Case 233.6: In-flight proposal cancellation handling.
     */
    public function test_inflight_proposal_cancellation(): void
    {
        $prop = $this->service->createProposal('OPERATIONAL', 'Shift Roster Modernization', true);

        // Cast one vote
        $this->service->castVote($prop->proposal_code, 'VOTER-01', 'YES', 5.0);

        // Cancel proposal in-flight due to union consultation
        $cancelled = $this->service->cancelProposal($prop->proposal_code, 'Postponed for bipartite union consultation');
        $this->assertSame('CANCELLED', $cancelled->status);

        // Tallying a cancelled proposal yields invalid result, never passed/rejected
        $tally = $this->service->tallyProposal($prop->proposal_code);
        $this->assertSame('CANCELLED', $tally['status']);
        $this->assertFalse($tally['is_valid_result']);
    }

    /**
     * (d) Execution blocked without mandatory safety review clearance (233.3 & 233.5).
     */
    public function test_execution_requires_safety_review_clearance(): void
    {
        $prop = $this->service->createProposal('TECHNICAL', 'Upgrade Smart District IoT Protocol', true);

        // Cast sufficient votes to pass: 60 weight YES out of 60 participated in 100 eligible
        $this->service->castVote($prop->proposal_code, 'VOTER-TECH', 'YES', 60.0);

        $tally = $this->service->tallyProposal($prop->proposal_code, 100);
        $this->assertSame('PASSED', $tally['status']);

        // 1. Attempting execution without safety review -> Throws exception
        try {
            $this->service->executeProposal($prop->proposal_code);
            $this->fail('Expected exception when executing without safety review clearance.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('requires formal safety review clearance', $e->getMessage());
        }

        // 2. Grant safety review -> Execution succeeds
        $this->service->grantSafetyReview($prop->proposal_code);
        $executed = $this->service->executeProposal($prop->proposal_code);
        $this->assertSame('EXECUTED', $executed->status);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_governance_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
