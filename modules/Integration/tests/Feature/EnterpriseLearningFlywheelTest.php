<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseLearningFlywheelService;
use Tests\TestCase;

class EnterpriseLearningFlywheelTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseLearningFlywheelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseLearningFlywheelService::class);
    }

    public function test_learning_loop_closure_and_cross_line_exchange_flow(): void
    {
        // 483.1 Initiate learning loop from Workshop warranty inspection
        $loop = $this->service->initiateLoop(
            code: 'LOOP-WARRANTY-TORQUE-01',
            line: 'LINE-CENTRAL-WORKSHOP',
            insight: 'Pneumatic tool pressure variation caused 3% overtorque on wheel studs',
            decision: 'Install automated digital pressure regulators on all workshop lines',
            outcome: 'Overtorque warranty claims reduced to 0 for 90 consecutive days',
            reviewDue: now()->addMonths(6)->toDateString()
        );

        $this->assertEquals('LOOP-WARRANTY-TORQUE-01', $loop->loop_code);
        $this->assertEquals('loop_open', $loop->status);

        // Close learning loop by codifying practice
        $closed = $this->service->closeLearningLoop('LOOP-WARRANTY-TORQUE-01');
        $this->assertEquals('loop_closed', $closed->status);
        $this->assertTrue((bool) $closed->practice_codified);

        // 483.3 Record cross-line knowledge exchange
        $exch = $this->service->recordCrossLineExchange(
            code: 'EXCH-WORKSHOP-TO-RENTAL',
            sourceLine: 'LINE-CENTRAL-WORKSHOP',
            targetLine: 'LINE-CAR-RENTAL',
            practitioners: 28,
            adoptionRate: 94.50
        );

        $this->assertEquals(28, $exch->participating_practitioners_count);
        $this->assertEquals(94.50, (float) $exch->adoption_rate_percentage);

        // 483.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_remediate_broken_loop_and_empty_note_blocked_edge_cases(): void
    {
        // 483.5 Edge case: Broken loop stage remediation
        $this->service->initiateLoop(
            'LOOP-STALLED-01',
            'LINE-EV-CHARGING',
            'Connector wear',
            'Order new batch',
            'Parts stuck at customs',
            now()->addDays(30)->toDateString()
        );

        // Empty note is blocked
        try {
            $this->service->remediateBrokenLoop('LOOP-STALLED-01', '');
            $this->fail('Expected exception for empty remediation note');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Remediation actions must be explicitly recorded', $e->getMessage());
        }

        // Remediate with explicit action
        $remediated = $this->service->remediateBrokenLoop('LOOP-STALLED-01', 'Air freight local certified alternative batch');
        $this->assertEquals('broken_remediated', $remediated->status);
        $this->assertTrue((bool) $remediated->practice_codified);
    }
}
