<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SocialImpactCommunityProgramService;
use Tests\TestCase;

class SocialImpactCommunityProgramTest extends TestCase
{
    use RefreshDatabase;

    protected SocialImpactCommunityProgramService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SocialImpactCommunityProgramService::class);
    }

    public function test_community_program_and_benefit_sharing_payout_flow(): void
    {
        // 444.1 Register community vocational education & scholarship program
        $prog = $this->service->registerProgram(
            programCode: 'CSR-VOCATIONAL-SUMATRA',
            communityName: 'Desa Mandiri Sejahtera',
            totalBudget: 1200000000.00,
            needAssessmentCompleted: true
        );

        $this->assertEquals('CSR-VOCATIONAL-SUMATRA', $prog->program_code);
        $this->assertEquals('active', $prog->status);

        // 444.2 Schedule benefit sharing payout with community consent
        $payout = $this->service->scheduleBenefitPayout(
            payoutCode: 'PAY-CSR-TR1',
            programCode: 'CSR-VOCATIONAL-SUMATRA',
            calculatedAmount: 300000000.00,
            communityConsent: true
        );

        $this->assertEquals('PAY-CSR-TR1', $payout->payout_code);
        $this->assertFalse((bool) $payout->is_paid);

        // 444.4 Release payout with verified implementation milestone
        $released = $this->service->releaseBenefitPayout('PAY-CSR-TR1', true);
        $this->assertTrue((bool) $released->is_paid);
        $this->assertTrue((bool) $released->milestone_gate_verified);

        // 444.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_missing_community_consent_and_paused_program_payout_blocked_edge_cases(): void
    {
        $this->service->registerProgram('CSR-WATER-WELL', 'Desa Air Bersih', 500000000.00);

        // 444.6 Risk: Benefit sharing without community consent is blocked
        try {
            $this->service->scheduleBenefitPayout('PAY-NO-CONSENT', 'CSR-WATER-WELL', 100000000.00, false);
            $this->fail('Expected exception for unconsented formula change');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires formal community council consent', $e->getMessage());
        }

        // Schedule valid payout
        $this->service->scheduleBenefitPayout('PAY-WELL-TR1', 'CSR-WATER-WELL', 100000000.00, true);

        // 444.5 Edge case: Program failing outcome evaluation pauses program and blocks payout
        $this->service->flagFailedOutcome('CSR-WATER-WELL');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('failed outcome evaluation and is currently paused for redesign');

        $this->service->releaseBenefitPayout('PAY-WELL-TR1', true);
    }
}
