<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ProblemManagementRootCauseService;
use Tests\TestCase;

class ProblemManagementRootCauseTest extends TestCase
{
    use RefreshDatabase;

    protected ProblemManagementRootCauseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProblemManagementRootCauseService::class);
    }

    public function test_problem_investigation_and_rca_closure_flow(): void
    {
        // 428.1 Major incident to problem linkage
        $prob = $this->service->openProblemFromIncident(
            problemCode: 'PRB-PAY-TIMEOUT-01',
            incidentCode: 'INC-2026-9921',
            serviceName: 'PaymentGateway'
        );

        $this->assertEquals('PRB-PAY-TIMEOUT-01', $prob->problem_code);
        $this->assertEquals('investigating', $prob->status);

        // 428.3 & 428.6 Document known error workaround with expiry date
        $known = $this->service->registerWorkaround(
            problemCode: 'PRB-PAY-TIMEOUT-01',
            workaround: 'Switch traffic to secondary acquiring bank connection',
            expiryDate: '2026-11-01'
        );

        $this->assertEquals('known_error', $known->status);
        $this->assertEquals('2026-11-01', $known->workaround_expiry_date);

        // 428.2 & 428.4 Close problem with 5-Whys and verified effectiveness
        $closed = $this->service->closeProblemWithRca(
            problemCode: 'PRB-PAY-TIMEOUT-01',
            fiveWhys: '1. Socket pool depleted -> 2. Leaked keep-alive connections -> 3. Missing connection cleanup in exception handler -> 4. Unhandled socket reset -> 5. Missing unit test for TCP RST',
            effectivenessVerified: true
        );

        $this->assertEquals('closed', $closed->status);
        $this->assertTrue((bool) $closed->effectiveness_verified);

        // 428.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_unverified_closure_blocked_and_unknown_root_cause_escalation_edge_cases(): void
    {
        $this->service->openProblemFromIncident('PRB-MYSTERY', 'INC-MYSTERY', 'OrderSvc');

        // 428.4 Attempting to close without verified effectiveness is blocked
        try {
            $this->service->closeProblemWithRca('PRB-MYSTERY', 'Temporary server reboot fixed it', false);
            $this->fail('Expected exception for unverified effectiveness closure');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires verified permanent countermeasure effectiveness', $e->getMessage());
        }

        // 428.5 Edge case: Root cause not found cannot be closed; must escalate to engineering review
        $escalated = $this->service->escalateUnknownRootCause('PRB-MYSTERY');
        $this->assertEquals('escalated', $escalated->status);
        $this->assertTrue((bool) $escalated->escalated_to_engineering_review);
    }
}
