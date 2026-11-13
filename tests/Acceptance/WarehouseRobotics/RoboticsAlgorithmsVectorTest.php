<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Assignment\HungarianSolver;
use Modules\Wms\Domain\Algorithms\AutomationNpvCalculator;
use Modules\Wms\Domain\Algorithms\ChargingBayContentionResolver;
use Modules\Wms\Domain\Algorithms\RobotBatteryDegradationTracker;
use Modules\Wms\Domain\Algorithms\RobotEnergyConsumptionEstimator;
use Modules\Wms\Domain\Algorithms\RobotFleetDispatchOptimizer;
use Modules\Wms\Domain\Algorithms\RobotMaintenanceWindowPlanner;
use Modules\Wms\Domain\Algorithms\TrafficDeadlockDetector;
use Modules\Wms\Domain\Algorithms\WaveBinPacker;
use Modules\Wms\Domain\Algorithms\WaveCutoffSlackCalculator;
use Modules\Wms\Domain\Algorithms\ZoneReservationScheduler;
use Modules\Wms\Domain\Certificates\AutomationNpvCertificate;
use Modules\Wms\Domain\Certificates\BatteryDegradationCertificate;
use Modules\Wms\Domain\Certificates\RobotEnergyCertificate;
use Modules\Wms\Domain\Certificates\TrafficDeadlockCertificate;
use Modules\Wms\Domain\Certificates\WaveBinPackerCertificate;
use Modules\Wms\Domain\Certificates\ZoneReservationCertificate;
use Modules\Wms\Domain\Services\RoboticsMissionPriorityPolicy;
use Modules\Wms\Domain\Services\RoboticsZoneInterlockEvaluator;
use Modules\Wms\Domain\ValueObjects\IntervalWindow;

test('vektor J1 ZoneReservationScheduler, J2 WaveBinPacker, J3 AutomationNpvCalculator, Hungarian, dan TrafficDeadlockDetector lulus persis', function (): void {
    // J1: ZoneReservationScheduler
    $j1 = new ZoneReservationScheduler;
    $resBase = [
        ['start' => 0, 'end' => 5],
        ['start' => 7, 'end' => 10],
    ];

    $r1 = $j1->schedule(1, $resBase, [], 3, 4);
    expect([$r1['start'], $r1['end']])->toBe([10, 13]);

    $r2 = $j1->schedule(1, $resBase, [], 2, 4);
    expect([$r2['start'], $r2['end']])->toBe([5, 7]);

    $r3 = $j1->schedule(1, $resBase, [], 3, 0);
    expect([$r3['start'], $r3['end']])->toBe([10, 13]);

    $resWithGap = array_merge($resBase, [['start' => 5, 'end' => 7]]);
    $r4 = $j1->schedule(1, $resWithGap, [], 1, 0);
    expect([$r4['start'], $r4['end']])->toBe([10, 11]);

    $cap2Res = [
        ['start' => 0, 'end' => 5],
        ['start' => 2, 'end' => 6],
    ];
    $r5 = $j1->schedule(2, $cap2Res, [], 3, 0);
    expect([$r5['start'], $r5['end']])->toBe([5, 8]);

    $r6 = $j1->schedule(2, $cap2Res, [], 2, 0);
    expect([$r6['start'], $r6['end']])->toBe([0, 2]);

    $r7 = $j1->schedule(2, [], [['start' => 4, 'end' => 8]], 3, 3);
    expect([$r7['start'], $r7['end']])->toBe([8, 11]);

    expect(fn () => $j1->schedule(1, [], [], 0, 0))
        ->toThrow(InvalidArgumentException::class, ZoneReservationScheduler::ERROR_INVALID_DURATION);

    // J2: WaveBinPacker
    $j2 = new WaveBinPacker;
    $orders1 = [
        ['id' => 'o1', 'size' => 40, 'carrier' => 'C1', 'cutoff' => 100],
        ['id' => 'o2', 'size' => 35, 'carrier' => 'C1', 'cutoff' => 100],
        ['id' => 'o3', 'size' => 30, 'carrier' => 'C1', 'cutoff' => 100],
        ['id' => 'o4', 'size' => 25, 'carrier' => 'C1', 'cutoff' => 100],
        ['id' => 'o5', 'size' => 20, 'carrier' => 'C1', 'cutoff' => 100],
        ['id' => 'o6', 'size' => 10, 'carrier' => 'C1', 'cutoff' => 100],
    ];
    $w1 = $j2->pack(70, $orders1)['waves'];
    expect(count($w1))->toBe(3)
        ->and(array_column($w1[0]['items'], 'size'))->toBe([40, 30])
        ->and(array_column($w1[1]['items'], 'size'))->toBe([35, 25, 10])
        ->and(array_column($w1[2]['items'], 'size'))->toBe([20]);

    $w2 = $j2->pack(70, [
        ['id' => 'a', 'size' => 50, 'carrier' => 'C1', 'cutoff' => 100],
        ['id' => 'b', 'size' => 50, 'carrier' => 'C1', 'cutoff' => 100],
        ['id' => 'c', 'size' => 50, 'carrier' => 'C1', 'cutoff' => 100],
    ])['waves'];
    expect(count($w2))->toBe(3);

    $w3 = $j2->pack(70, [['id' => 'big', 'size' => 80, 'carrier' => 'C1', 'cutoff' => 100]])['waves'];
    expect($w3[0]['status'])->toBe(WaveBinPacker::STATUS_OVERSIZE);

    $w4 = $j2->pack(70, [
        ['id' => 'x1', 'size' => 10, 'carrier' => 'X', 'cutoff' => 100],
        ['id' => 'y1', 'size' => 10, 'carrier' => 'Y', 'cutoff' => 100],
    ])['waves'];
    expect(count($w4))->toBe(2);

    // J3: AutomationNpvCalculator
    $j3 = new AutomationNpvCalculator;
    $cf = [400_000_000, 400_000_000, 400_000_000, 400_000_000];

    $n1 = $j3->evaluate(1_000_000_000, $cf, 1, 10);
    expect($n1['npv'])->toBe(267_946_179)
        ->and($n1['payback_months'])->toBe(30);

    $n2 = $j3->evaluate(1_000_000_000, $cf, 1, 5);
    expect($n2['npv'])->toBe(35_493_827);

    $n3 = $j3->evaluate(1_000_000_000, $cf, 1, 4);
    expect($n3['npv'])->toBe(-55_360_000);

    $n4 = $j3->evaluate(1_000_000_000, [100_000_000, 100_000_000, 100_000_000, 100_000_000], 1, 10);
    expect($n4['payback_status'])->toBe(AutomationNpvCalculator::PAYBACK_NONE)
        ->and($n4['payback_months'])->toBeNull();

    expect(fn () => $j3->evaluate(1_000_000_000, $cf, 0, 1))
        ->toThrow(InvalidArgumentException::class, AutomationNpvCalculator::ERROR_INVALID_RATE);

    // Hungarian Shared
    $hungarian = new HungarianSolver;
    $hRes = $hungarian->solve([[4, 1, 3], [2, 0, 5], [3, 2, 2]]);
    expect($hRes->assignment)->toBe([1, 0, 2])
        ->and($hRes->totalCost)->toBe(5);

    // TrafficDeadlockDetector
    $deadlock = new TrafficDeadlockDetector;
    $cycle = $deadlock->detectAndResolve(
        ['M-01' => 'M-02', 'M-02' => 'M-03', 'M-03' => 'M-01'],
        ['M-01' => 90, 'M-02' => 20, 'M-03' => 50],
    );
    expect($cycle['has_deadlock'])->toBeTrue()
        ->and($cycle['victim_node'])->toBe('M-02');
});

test('sertifikat rusak J1 J2 J3 dan Deadlock melempar exception', function (): void {
    $j1 = new ZoneReservationScheduler;
    $c1 = $j1->schedule(1, [], [], 2, 0)['certificate'];
    $tampered1 = new ZoneReservationCertificate($c1->canonicalInput, $c1->steps, ['start' => 99, 'end' => 101], $c1->sha256);
    expect(fn () => $tampered1->verifyOrFail())->toThrow(InvalidArgumentException::class);

    $j2 = new WaveBinPacker;
    $c2 = $j2->pack(70, [['id' => 'o1', 'size' => 10, 'carrier' => 'C1', 'cutoff' => 100]])['certificate'];
    $tampered2 = new WaveBinPackerCertificate($c2->canonicalInput, $c2->steps, ['waves' => []], $c2->sha256);
    expect(fn () => $tampered2->verifyOrFail())->toThrow(InvalidArgumentException::class);

    $j3 = new AutomationNpvCalculator;
    $c3 = $j3->evaluate(1_000_000_000, [400_000_000, 400_000_000, 400_000_000, 400_000_000], 1, 10)['certificate'];
    $tampered3 = new AutomationNpvCertificate($c3->canonicalInput, $c3->steps, ['npv' => 0], $c3->sha256);
    expect(fn () => $tampered3->verifyOrFail())->toThrow(InvalidArgumentException::class);

    $dl = new TrafficDeadlockDetector;
    $c4 = $dl->detectAndResolve(['M-01' => 'M-02', 'M-02' => 'M-01'], ['M-01' => 10, 'M-02' => 20])['certificate'];
    $tampered4 = new TrafficDeadlockCertificate($c4->canonicalInput, $c4->steps, ['has_deadlock' => false, 'cycle_nodes' => [], 'victim_node' => null], $c4->sha256);
    expect(fn () => $tampered4->verifyOrFail())->toThrow(InvalidArgumentException::class);

    $estimator = new RobotEnergyConsumptionEstimator;
    $energyCert = $estimator->estimateBatch(101, 80, [
        ['mission_id' => 1, 'distance_m' => 100, 'payload_kg' => 10],
        ['mission_id' => 2, 'distance_m' => 250, 'payload_kg' => 25],
    ]);
    expect($energyCert->allFeasible)->toBeTrue()
        ->and($energyCert->totalDrainPct)->toBeGreaterThan(0)
        ->and($estimator->maxSafeRangeMeters(80, 10))->toBeGreaterThan(1000);

    $tamperedEnergy = new RobotEnergyCertificate(
        $energyCert->robotId,
        $energyCert->initialBatteryPct,
        $energyCert->reserveMinBatteryPct,
        $energyCert->totalDrainPct + 5,
        $energyCert->projectedEndBatteryPct,
        $energyCert->totalEnergyMwh,
        $energyCert->allFeasible,
        $energyCert->items,
        $energyCert->signatureHash,
    );
    expect(fn () => $tamperedEnergy->verify())->toThrow(InvalidArgumentException::class);

    $interlock = new RoboticsZoneInterlockEvaluator;
    $blockedRes = $interlock->evaluateEntry('Z-BLK', 1, true, 0, 30, new IntervalWindow(5, 10), [], 90);
    expect($blockedRes['allowed'])->toBeFalse()->and($blockedRes['reason_code'])->toBe('STAFF_ZONE_BLOCKED');
    $clearRes = $interlock->evaluateEntry('Z-OK', 2, false, null, null, new IntervalWindow(5, 10), [['mission_id' => 9, 'start_min' => 4, 'end_min' => 8, 'robot_id' => 1]], 90);
    expect($clearRes['allowed'])->toBeTrue()->and($clearRes['recommended_speed_mm_s'])->toBe(RoboticsZoneInterlockEvaluator::MAX_COEXISTENCE_SPEED_MM_S);

    $optimizer = new RobotFleetDispatchOptimizer(new HungarianSolver, $estimator, $interlock);
    $optPlan = $optimizer->optimize(
        [
            ['mission_id' => 1, 'zone_code' => 'Z-OK', 'priority' => 90, 'distance_m' => 50, 'payload_kg' => 5, 'start_min' => 0, 'duration_min' => 4, 'staff_blocked' => false, 'capacity_k' => 1, 'active_reservations' => []],
            ['mission_id' => 2, 'zone_code' => 'Z-BLK', 'priority' => 50, 'distance_m' => 50, 'payload_kg' => 5, 'start_min' => 0, 'duration_min' => 4, 'staff_blocked' => true, 'capacity_k' => 1, 'active_reservations' => []],
        ],
        [
            ['robot_id' => 10, 'battery_pct' => 90, 'position_x' => 1, 'position_y' => 1, 'is_online' => true],
        ],
    );
    expect(count($optPlan['assignments']))->toBe(1)
        ->and($optPlan['assignments'][0]['mission_id'])->toBe(1)
        ->and($optPlan['unassigned_mission_ids'])->toBe([2]);

    $batteryTracker = new RobotBatteryDegradationTracker;
    $batCert = $batteryTracker->evaluateFleet([
        ['robot_id' => 1, 'cycle_count' => 1200, 'deep_discharge_events' => 5],
        ['robot_id' => 2, 'cycle_count' => 2600, 'deep_discharge_events' => 2],
    ]);
    expect($batCert->replacementCount)->toBe(1)
        ->and($batCert->fleetAvgHealthPermil)->toBe(799);
    $tamperedBat = new BatteryDegradationCertificate($batCert->totalRobots, 0, $batCert->fleetAvgHealthPermil, $batCert->robots, $batCert->signatureHash);
    expect(fn () => $tamperedBat->verify())->toThrow(InvalidArgumentException::class);

    $slackCalc = new WaveCutoffSlackCalculator;
    $slacks = $slackCalc->evaluate([
        ['wave_id' => 10, 'carrier_code' => 'JNE', 'cutoff_hhmm' => 1700, 'estimated_pick_min' => 40],
    ], 1600);
    expect($slacks[0]['slack_minutes'])->toBe(20)->and($slacks[0]['urgency'])->toBe('CRITICAL');

    $prioPolicy = new RoboticsMissionPriorityPolicy;
    expect($prioPolicy->computeEffectivePriority(50, 25, 1, true))->toBe(78)
        ->and($prioPolicy->shouldEscalate('queued', 50, 0))->toBeTrue();

    $bayResolver = new ChargingBayContentionResolver;
    $bays = $bayResolver->assignBays([
        ['robot_id' => 1, 'window_start_min' => 0, 'window_end_min' => 20],
        ['robot_id' => 2, 'window_start_min' => 5, 'window_end_min' => 25],
        ['robot_id' => 3, 'window_start_min' => 10, 'window_end_min' => 30],
    ], 2);
    expect(count($bays['assigned']))->toBe(2)->and($bays['deferred_robot_ids'])->toBe([3]);

    $maintPlanner = new RobotMaintenanceWindowPlanner;
    $maint = $maintPlanner->plan([
        ['robot_id' => 1, 'health_score' => 500, 'preferred_start_min' => 60, 'duration_min' => 30],
        ['robot_id' => 2, 'health_score' => 650, 'preferred_start_min' => 70, 'duration_min' => 30],
    ], 10);
    expect(count($maint['scheduled']))->toBe(1)->and($maint['deferred_robot_ids'])->toBe([2]);
});
