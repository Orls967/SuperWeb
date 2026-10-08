<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\AcademicIndustryResearchService;
use Tests\TestCase;

class AcademicIndustryResearchTest extends TestCase
{
    use RefreshDatabase;

    protected AcademicIndustryResearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AcademicIndustryResearchService::class);
    }

    public function test_research_project_ip_agreement_mandatory_prerequisite(): void
    {
        // 1. Without IP terms -> stays DRAFT_PENDING_IP (265.5 Edge Case)
        $draftProj = $this->service->initiateResearchProject(
            projectCode: 'RES-EV-UI-01',
            universityPartner: 'Universitas Indonesia',
            researchTitle: 'Solid-State Battery Anode Degradation Study',
            ipOwnershipTerms: null, // Undefined IP!
            sandboxExpiryDate: now()->addYear()->toDateString()
        );
        $this->assertEquals('DRAFT_PENDING_IP', $draftProj->status);
        $this->assertFalse((bool) $draftProj->is_ip_agreement_signed);

        // Funding tranche cannot be released without signed IP agreement
        try {
            $this->service->releaseFundingTranche((int) $draftProj->id, 1, 50000.0, 'Literature Review');
            $this->fail('Expected exception for missing IP agreement');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('signed IP agreement', $e->getMessage());
        }

        // 2. With IP terms -> APPROVED_ACTIVE and funding released (265.1 & 265.4)
        $activeProj = $this->service->initiateResearchProject(
            projectCode: 'RES-BIO-ITB-02',
            universityPartner: 'Institut Teknologi Bandung',
            researchTitle: 'Bio-Nickel Leaching Optimization',
            ipOwnershipTerms: 'Joint IP: 60% AutoServe Industrial, 40% ITB Research Foundation',
            sandboxExpiryDate: now()->addYear()->toDateString()
        );
        $this->assertEquals('APPROVED_ACTIVE', $activeProj->status);
        $this->assertTrue((bool) $activeProj->is_ip_agreement_signed);

        $tranche = $this->service->releaseFundingTranche((int) $activeProj->id, 1, 75000.0, 'Pilot Bioreactor Benchmarking');
        $this->assertTrue((bool) $tranche->is_released);
    }

    public function test_research_sandbox_automatic_expiry_and_isolation(): void
    {
        // Expired sandbox automatically flagged without leaking data (265.4 & 265.6)
        $proj = $this->service->initiateResearchProject(
            projectCode: 'RES-SANDBOX-EXPIRED',
            universityPartner: 'UGM',
            researchTitle: 'Geothermal Turbine Fluid Dynamics',
            ipOwnershipTerms: 'Standard Academic Terms',
            sandboxExpiryDate: now()->subDays(10)->toDateString()
        );

        $expired = $this->service->enforceSandboxExpiry('RES-SANDBOX-EXPIRED');
        $this->assertTrue((bool) $expired->sandbox_expired);
        $this->assertTrue((bool) $expired->is_production_data_isolated);
    }

    public function test_innovation_challenge_four_eyes_evaluation(): void
    {
        // 1. Same evaluator rejected (265.3)
        try {
            $this->service->conductChallengeEvaluation(
                challengeCode: 'CHAL-CLEAN-AIR-01',
                problemBrief: 'Smelter particulate filtration',
                evaluator1Id: 'PROF_HENDRA',
                evaluator2Id: 'PROF_HENDRA', // Same!
                winnerSubmissionCode: 'SUB-99',
                totalPrizeUsd: 25000.0
            );
            $this->fail('Expected exception for four-eyes violation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Four-eyes validation failed', $e->getMessage());
        }

        // 2. Distinct evaluators succeed
        $challenge = $this->service->conductChallengeEvaluation(
            challengeCode: 'CHAL-CLEAN-AIR-01',
            problemBrief: 'Smelter particulate filtration',
            evaluator1Id: 'PROF_HENDRA',
            evaluator2Id: 'DR_SARI',
            winnerSubmissionCode: 'SUB-99',
            totalPrizeUsd: 25000.0
        );
        $this->assertNotNull($challenge);
        $this->assertFalse((bool) $challenge->payout_released);
    }

    public function test_challenge_prize_payout_held_until_implementation_verified(): void
    {
        $this->service->conductChallengeEvaluation(
            challengeCode: 'CHAL-EV-CHARGING-02',
            problemBrief: 'Smart grid peak load balancing',
            evaluator1Id: 'EVAL_A',
            evaluator2Id: 'EVAL_B',
            winnerSubmissionCode: 'SUB-SMART-GRID-101',
            totalPrizeUsd: 50000.0
        );

        // 1. Implementation failure holds final payout (265.7 Edge Case)
        try {
            $this->service->releaseChallengePrize('CHAL-EV-CHARGING-02', false);
            $this->fail('Expected exception for unverified implementation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Implementation follow-through not yet verified', $e->getMessage());
        }

        // 2. Verified implementation releases payout
        $released = $this->service->releaseChallengePrize('CHAL-EV-CHARGING-02', true);
        $this->assertTrue((bool) $released->implementation_verified);
        $this->assertTrue((bool) $released->payout_released);
    }

    public function test_academic_research_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $proj = $this->service->initiateResearchProject('RES-AUD-1', 'ITB', 'Audit Study', 'Terms OK', now()->addYear()->toDateString());
        $this->service->releaseFundingTranche((int) $proj->id, 1, 1000.0, 'M1');
        $this->service->conductChallengeEvaluation('CHAL-AUD-1', 'Brief', 'E1', 'E2', 'SUB-1', 1000.0);
        $this->service->releaseChallengePrize('CHAL-AUD-1', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: prize payout released without implementation verified
        DB::table('academic_innovation_challenges')->insert([
            'challenge_code' => 'CHAL-UNVERIFIED-PAID',
            'problem_brief' => 'Flawed Challenge',
            'evaluator_1_id' => 'E1',
            'evaluator_2_id' => 'E2',
            'winner_submission_code' => 'SUB-BAD',
            'total_prize_usd' => 10000.0,
            'implementation_verified' => false, // Discrepancy: paid without verification!
            'payout_released' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
