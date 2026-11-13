<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Assignment\AssignmentCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Assignment\HungarianSolver;
use Modules\Wms\Domain\Algorithms\AutomationRoiPlanner;
use Modules\Wms\Domain\Algorithms\RobotTaskAllocator;
use Modules\Wms\Domain\Algorithms\TrafficDeadlockDetector;
use Modules\Wms\Domain\Algorithms\WaveAutomationDispatcher;
use Modules\Wms\Domain\Exceptions\RobotMissionTransitionException;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;

it('computes deterministic integer ROI and payback without floats in J1 AutomationRoiPlanner', function (): void {
    $planner = new AutomationRoiPlanner;
    $res = $planner->plan('WH-01', 1_000_000_000, 360_000_000, 650);

    expect($res['roi_bps'])->toBe(3600)
        ->and($res['payback_months'])->toBe(34)
        ->and($res['recommended_stage'])->toBe('phase_3_asrs_conveyor')
        ->and($res['certificate']->isValid())->toBeTrue();
});

it('allocates robots to tasks deterministically via Shared HungarianSolver in J2 RobotTaskAllocator', function (): void {
    $allocator = new RobotTaskAllocator(new HungarianSolver, new AssignmentCertificateVerifier);
    $res = $allocator->allocate(
        robots: [
            ['id' => 1, 'code' => 'AMR-1', 'zone' => 'Z1', 'battery_pct' => 95],
            ['id' => 2, 'code' => 'AMR-2', 'zone' => 'Z2', 'battery_pct' => 90],
        ],
        tasks: [
            ['task_code' => 'T-B', 'zone' => 'Z2', 'distance_m' => 5],
            ['task_code' => 'T-A', 'zone' => 'Z1', 'distance_m' => 5],
        ],
    );

    expect($res['certificate']->isValid())->toBeTrue()
        ->and($res['assignments'])->toHaveCount(2);

    $byRobot = collect($res['assignments'])->keyBy('robot_id');
    expect($byRobot[1]['task_code'])->toBe('T-A')
        ->and($byRobot[2]['task_code'])->toBe('T-B');
});

it('plans hybrid and manual fallback modes in J3 WaveAutomationDispatcher', function (): void {
    $dispatcher = new WaveAutomationDispatcher;

    $hybrid = $dispatcher->planWaveDispatch(
        waveCode: 'W-HYB',
        tasks: [
            ['task_code' => 'T1', 'zone' => 'Z1', 'priority' => 2],
            ['task_code' => 'T2', 'zone' => 'Z2', 'priority' => 1],
        ],
        availableRobotIds: [10],
    );

    expect($hybrid['mode'])->toBe('hybrid')
        ->and($hybrid['automated_tasks'])->toBe(['T2'])
        ->and($hybrid['manual_fallback_tasks'])->toBe(['T1'])
        ->and($hybrid['certificate']->isValid())->toBeTrue();

    $manual = $dispatcher->planWaveDispatch('W-MAN', [['task_code' => 'T1', 'zone' => 'Z1', 'priority' => 1]], []);
    expect($manual['mode'])->toBe('manual_fallback')
        ->and($manual['certificate']->isValid())->toBeTrue();
});

it('detects cyclic deadlocks and non-cyclic chains in TrafficDeadlockDetector', function (): void {
    $detector = new TrafficDeadlockDetector;

    $acyclic = $detector->detectCycle(['R1' => 'R2', 'R2' => 'R3']);
    expect($acyclic['has_deadlock'])->toBeFalse()
        ->and($acyclic['cycle_nodes'])->toBe([]);

    $cyclic = $detector->detectCycle(['R1' => 'R2', 'R2' => 'R3', 'R3' => 'R1']);
    expect($cyclic['has_deadlock'])->toBeTrue()
        ->and($cyclic['cycle_nodes'])->toBe(['R1', 'R2', 'R3']);
});

it('enforces all 8 states and rejects invalid transitions in RobotMissionStateMachine', function (): void {
    expect(RobotMissionStateMachine::allStates())->toHaveCount(8)
        ->and(RobotMissionStateMachine::canTransition('queued', 'assigned'))->toBeTrue()
        ->and(RobotMissionStateMachine::canTransition('moving', 'failed'))->toBeTrue()
        ->and(RobotMissionStateMachine::canTransition('failed', 'fallback_manual'))->toBeTrue()
        ->and(RobotMissionStateMachine::canTransition('completed', 'queued'))->toBeFalse();

    expect(fn () => RobotMissionStateMachine::assertTransition('completed', 'moving'))
        ->toThrow(RobotMissionTransitionException::class);
});
