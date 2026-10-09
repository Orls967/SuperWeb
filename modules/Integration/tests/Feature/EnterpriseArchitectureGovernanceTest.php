<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseArchitectureGovernanceService;
use Tests\TestCase;

class EnterpriseArchitectureGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseArchitectureGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseArchitectureGovernanceService::class);
    }

    public function test_architecture_review_board_and_radar_flow(): void
    {
        // 472.3 Register Technology in Trial ring
        $radar = $this->service->registerTechRadar(
            techName: 'KAFKA-EVENT-STREAM',
            ring: 'trial',
            owner: 'Staff Platform Architect',
            reviewDue: now()->addMonths(6)->toDateString()
        );

        $this->assertEquals('KAFKA-EVENT-STREAM', $radar->tech_name);

        // 472.2 & 472.4 Submit ADR Proposal adhering to modular principles
        $adr = $this->service->submitAdrProposal(
            adrCode: 'ADR-042-ASYNC-EVENTS',
            title: 'Adopt Kafka for Cross-Line Event-Driven Notifications',
            proposedTech: 'KAFKA-EVENT-STREAM',
            violatesCorePrinciples: false
        );

        $this->assertEquals('proposed', $adr->status);

        // ARB approval and post-implementation verification
        $approved = $this->service->approveAdr('ADR-042-ASYNC-EVENTS');
        $this->assertTrue((bool) $approved->adr_formally_approved);

        $verified = $this->service->verifyPostImplementation('ADR-042-ASYNC-EVENTS');
        $this->assertTrue((bool) $verified->post_implementation_verified);

        // 472.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_hold_tech_and_core_principle_violation_blocked_edge_cases(): void
    {
        // 472.3 Register deprecated tech on HOLD ring
        $this->service->registerTechRadar('MONGODB-LEDGER', 'hold', 'Data Architect', now()->addMonths(3)->toDateString());

        // Attempting ADR with HOLD tech is blocked
        try {
            $this->service->submitAdrProposal('ADR-BAD-TECH', 'Use Mongo for ledger', 'MONGODB-LEDGER');
            $this->fail('Expected exception for HOLD technology adoption');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('is marked as \'HOLD\' on Enterprise Tech Radar', $e->getMessage());
        }

        // 472.1 & 472.6 Violation of core architectural principles is blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Proposal violates fundamental architectural principles');

        $this->service->submitAdrProposal(
            adrCode: 'ADR-BYPASS-LEDGER',
            title: 'Direct SQL insert bypassing double-entry ledger',
            proposedTech: 'MYSQL-DIRECT',
            violatesCorePrinciples: true // Core principle violation!
        );
    }
}
