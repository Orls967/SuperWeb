<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\BoardGovernanceDoaService;
use Tests\TestCase;

/**
 * Fase 231 — Tata Kelola: Board, Committee & Delegation System Tests
 *
 * Covers:
 *  (a) Delegation of Authority (DoA) limit enforcement and rejection of unauthorized approvals
 *  (b) Edge Case 231.6: Meeting adjournment protocol when statutory quorum is not achieved
 *  (c) Edge Case 231.7: Conflict of interest declaration with designated alternate board member
 *  (d) Abstention impact on quorum base calculation
 *  (e) Quality audit gov:audit clean with 0 discrepancies
 */
class BoardGovernanceDoaTest extends TestCase
{
    use RefreshDatabase;

    protected BoardGovernanceDoaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BoardGovernanceDoaService::class);
    }

    /**
     * (a) Delegation of Authority (DoA) matrix threshold check (231.2 & 231.5).
     */
    public function test_doa_matrix_enforcement(): void
    {
        // CEO limit for CAPEX is 5B IDR, Board limit is 50B IDR
        $this->service->setDoaLimit('CAPEX', 'CEO', 0, 5000000000);
        $this->service->setDoaLimit('CAPEX', 'BOARD_OF_DIRECTORS', 5000000000, 50000000000);

        // CEO approving 3B -> OK
        $this->assertTrue($this->service->validateDoaCompliance('CAPEX', 'CEO', 3000000000));

        // CEO attempting to approve 7B -> Exceeds limit -> Exception
        try {
            $this->service->recordDecision(
                'CAPEX',
                7000000000,
                'CEO',
                'CEO_OFFICER'
            );
            $this->fail('Expected exception for CEO exceeding DoA limit.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds allowable threshold', $e->getMessage());
        }

        // Board of Directors approving 7B -> OK
        $dec = $this->service->recordDecision(
            'CAPEX',
            7000000000,
            'BOARD_OF_DIRECTORS',
            'CHAIRMAN_BOARD'
        );
        $this->assertSame('CAPEX', $dec->decision_type);
        $this->assertTrue((bool) $dec->is_immutable);
    }

    /**
     * (b) Edge Case 231.6: Quorum shortfall causes meeting adjournment.
     */
    public function test_board_meeting_quorum_adjournment(): void
    {
        $this->service->setDoaLimit('CONTRACT', 'BOARD_OF_DIRECTORS', 0, 1000000000);

        // Board of 8 members, but only 2 attend (< 4 majority) -> Adjourned
        $meeting = $this->service->recordBoardMeeting(
            'MTG-2026-Q3-01',
            'BOARD',
            '2026-09-15',
            8,
            2,
            0
        );

        $this->assertSame('ADJOURNED_LACK_OF_QUORUM', $meeting->status);
        $this->assertFalse((bool) $meeting->quorum_achieved);
        $this->assertStringContainsString('failed to meet majority quorum', $meeting->adjournment_reason);

        // Attempting to record formal decision on an adjourned meeting -> Exception
        try {
            $this->service->recordDecision(
                'CONTRACT',
                1000000,
                'BOARD_OF_DIRECTORS',
                'BOARD_CHAIR',
                $meeting->meeting_code
            );
            $this->fail('Expected exception for recording decision in meeting lacking quorum.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('lacked quorum', $e->getMessage());
        }
    }

    /**
     * (c) & (d) Conflict of interest and designated alternate member (231.3 & 231.7).
     */
    public function test_conflict_of_interest_and_alternate_member(): void
    {
        // Director declares conflict with vendor 'ACME_CLOUD_LTD' and designates alternate
        $decl = $this->service->declareConflictOfInterest('DIR-TECH-01', 'ACME_CLOUD_LTD', 'DIR-ALT-02');
        $this->assertSame('ACTIVE', $decl->status);
        $this->assertTrue((bool) $decl->mandatory_abstain);
        $this->assertSame('DIR-ALT-02', $decl->alternate_member_id);

        // Quorum with 1 member abstained: 5 total, 1 abstained -> active base is 4 -> majority is 2
        $meeting = $this->service->recordBoardMeeting(
            'MTG-AUDIT-2026',
            'AUDIT',
            '2026-09-20',
            5,
            3, // 3 attended
            1  // 1 abstained
        );

        $this->assertSame(4, $meeting->active_voting_quorum);
        $this->assertTrue((bool) $meeting->quorum_achieved);
        $this->assertSame('QUORUM_MET', $meeting->status);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_board_governance_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
