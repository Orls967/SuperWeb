<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PolicyComplianceTestingRemediationService;
use Tests\TestCase;

class PolicyComplianceTestingRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected PolicyComplianceTestingRemediationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PolicyComplianceTestingRemediationService::class);
    }

    public function test_repeat_finding_systemic_redesign_and_closure_flow(): void
    {
        // 451.1 & 451.3 Log repeat finding on vendor sanction screening
        $f = $this->service->logFinding(
            code: 'FND-SANCTION-REPEAT-01',
            policyArea: 'aml_sanctions',
            isStatisticallyValidSample: true,
            isRepeatFinding: true
        );

        $this->assertEquals('FND-SANCTION-REPEAT-01', $f->finding_code);
        $this->assertTrue((bool) $f->is_repeat_finding);
        $this->assertEquals('open', $f->status);

        // 451.3, 451.4, 451.5 Close repeat finding with systemic RCA, control redesign, and independent verifier sign-off
        $closed = $this->service->closeFinding(
            code: 'FND-SANCTION-REPEAT-01',
            independentVerifierCheck: true,
            systemicRca: 'Batch screening window omitted real-time webhook updates for ad-hoc one-time payments',
            redesignedControl: 'Automated pre-clearing synchronous API gate implemented in payments orchestrator'
        );

        $this->assertEquals('verified_closed', $closed->status);
        $this->assertTrue((bool) $closed->independent_verifier_closed);

        // 451.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_invalid_sample_and_unverified_repeat_closure_blocked_edge_cases(): void
    {
        // 451.6 Risk: Statistically invalid sample is blocked
        try {
            $this->service->logFinding('FND-BAD-SAMPLE', 'privacy', false, false);
            $this->fail('Expected exception for statistically invalid sample');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot be derived from a statistically invalid/non-representative sample', $e->getMessage());
        }

        // 451.5 Edge case: Repeat finding without systemic RCA or control redesign is blocked
        $this->service->logFinding('FND-REPEAT-LAZY', 'procurement', true, true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be closed as an isolated incident');

        $this->service->closeFinding(
            code: 'FND-REPEAT-LAZY',
            independentVerifierCheck: true,
            systemicRca: null, // Missing RCA!
            redesignedControl: null
        );
    }
}
