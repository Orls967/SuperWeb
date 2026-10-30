<?php

declare(strict_types=1);

namespace Tests\Unit\Numerics;

use InvalidArgumentException;
use Modules\Shared\Domain\Numerics\TopologySuppressor;
use Modules\Shared\Domain\Numerics\TopologySuppressorVerifier;
use UnexpectedValueException;

test('topology suppressor exact vector: R -> S1, S2 and S1 -> L1, L2 yields 1 ticket for R while L1, L2 are suppressed', function () {
    $topology = [
        'R' => ['S1', 'S2'],
        'S1' => ['L1', 'L2'],
    ];

    $alarms = [
        ['id' => 'alm-1', 'node' => 'R', 'severity' => 'down'],
        ['id' => 'alm-2', 'node' => 'L1', 'severity' => 'down'],
        ['id' => 'alm-3', 'node' => 'L2', 'severity' => 'down'],
    ];

    $result = TopologySuppressor::suppress($topology, $alarms);

    expect($result['total_alarms'])->toBe(3)
        ->and($result['root_causes_count'])->toBe(1)
        ->and($result['suppressed_count'])->toBe(2)
        ->and($result['independent_count'])->toBe(0)
        ->and($result['tickets'])->toHaveCount(1);

    $ticket = $result['tickets'][0];
    expect($ticket['ticket_id'])->toBe('TKT-R')
        ->and($ticket['root_node'])->toBe('R')
        ->and($ticket['alarm_id'])->toBe('alm-1')
        ->and($ticket['severity'])->toBe('down')
        ->and($ticket['suppressed_alarm_ids'])->toEqualCanonicalizing(['alm-2', 'alm-3']);

    // Check alarm details
    $alarmMap = [];
    foreach ($result['alarms'] as $al) {
        $alarmMap[$al['id']] = $al;
    }

    expect($alarmMap['alm-1']['status'])->toBe('root_cause')
        ->and($alarmMap['alm-1']['ticket_id'])->toBe('TKT-R')
        ->and($alarmMap['alm-1']['root_cause_node'])->toBeNull()
        ->and($alarmMap['alm-1']['ancestor_path'])->toBeNull();

    expect($alarmMap['alm-2']['status'])->toBe('suppressed')
        ->and($alarmMap['alm-2']['ticket_id'])->toBe('TKT-R')
        ->and($alarmMap['alm-2']['root_cause_node'])->toBe('R')
        ->and($alarmMap['alm-2']['ancestor_path'])->toBe(['R', 'S1', 'L1']);

    expect($alarmMap['alm-3']['status'])->toBe('suppressed')
        ->and($alarmMap['alm-3']['ticket_id'])->toBe('TKT-R')
        ->and($alarmMap['alm-3']['root_cause_node'])->toBe('R')
        ->and($alarmMap['alm-3']['ancestor_path'])->toBe(['R', 'S1', 'L2']);

    expect($result['certificate']['topology_nodes_count'])->toBe(5)
        ->and($result['certificate']['topology_edges_count'])->toBe(4)
        ->and($result['certificate']['total_alarms'])->toBe(3)
        ->and($result['certificate']['root_causes_count'])->toBe(1)
        ->and($result['certificate']['suppressed_count'])->toBe(2)
        ->and($result['certificate']['tickets_count'])->toBe(1);

    expect(TopologySuppressorVerifier::verify($result, $topology))->toBeTrue();
});

test('topology suppressor supports edge list format and independent alarms', function () {
    $topology = [
        ['parent' => 'Core1', 'child' => 'Agg1'],
        ['parent' => 'Agg1', 'child' => 'Access1'],
        ['parent' => 'Core2', 'child' => 'Agg2'],
    ];

    $alarms = [
        ['id' => 'a1', 'node' => 'Agg1', 'severity' => 'critical'],
        ['id' => 'a2', 'node' => 'Access1', 'severity' => 'down'],
        ['id' => 'a3', 'node' => 'Core2', 'severity' => 'warning'], // not down
    ];

    $result = TopologySuppressor::suppress($topology, $alarms);

    expect($result['root_causes_count'])->toBe(1)
        ->and($result['suppressed_count'])->toBe(1)
        ->and($result['independent_count'])->toBe(1)
        ->and($result['tickets'])->toHaveCount(1)
        ->and($result['tickets'][0]['root_node'])->toBe('Agg1')
        ->and($result['tickets'][0]['ticket_id'])->toBe('TKT-Agg1')
        ->and($result['tickets'][0]['alarm_id'])->toBe('a1')
        ->and($result['tickets'][0]['severity'])->toBe('critical')
        ->and($result['tickets'][0]['suppressed_alarm_ids'])->toBe(['a2']);

    $alarmsById = array_column($result['alarms'], null, 'id');
    expect($alarmsById['a1']['status'])->toBe('root_cause');
    expect($alarmsById['a2']['status'])->toBe('suppressed');
    expect($alarmsById['a3']['status'])->toBe('independent');

    expect(TopologySuppressorVerifier::verify($result, $topology))->toBeTrue();
});

test('topology suppressor handles integer IDs and case-insensitive severities', function () {
    $topology = [
        1 => [2],
        2 => [3],
    ];

    $alarms = [
        ['id' => 101, 'node' => 1, 'severity' => 'DOWN'],
        ['id' => 102, 'node' => 3, 'severity' => 'CRITICAL'],
    ];

    $result = TopologySuppressor::suppress($topology, $alarms);

    expect($result['root_causes_count'])->toBe(1)
        ->and($result['suppressed_count'])->toBe(1)
        ->and($result['tickets'][0]['ticket_id'])->toBe('TKT-1')
        ->and($result['alarms'][0]['status'])->toBe('root_cause')
        ->and($result['alarms'][1]['status'])->toBe('suppressed')
        ->and($result['alarms'][1]['ancestor_path'])->toBe(['1', '2', '3']);

    expect(TopologySuppressorVerifier::verify($result, $topology))->toBeTrue();
});

test('topology suppressor handles custom down severities and multiple alarms on same node', function () {
    $topology = [
        'A' => ['B'],
    ];

    $alarms = [
        ['id' => 'a1', 'node' => 'A', 'severity' => 'fatal'],
        ['id' => 'a2', 'node' => 'A', 'severity' => 'fatal'],
        ['id' => 'b1', 'node' => 'B', 'severity' => 'fatal'],
    ];

    $result = TopologySuppressor::suppress($topology, $alarms, downSeverities: ['fatal']);

    expect($result['root_causes_count'])->toBe(2)
        ->and($result['suppressed_count'])->toBe(1)
        ->and($result['tickets'])->toHaveCount(1) // 1 ticket for node A
        ->and($result['tickets'][0]['suppressed_alarm_ids'])->toBe(['b1']);

    expect(TopologySuppressorVerifier::verify($result, $topology, downSeverities: ['fatal']))->toBeTrue();
});

test('topology suppressor detects cycles and throws exception', function () {
    $cyclicTopology = [
        'A' => ['B'],
        'B' => ['C'],
        'C' => ['A'],
    ];

    $alarms = [
        ['id' => 'a1', 'node' => 'A', 'severity' => 'down'],
    ];

    expect(fn () => TopologySuppressor::suppress($cyclicTopology, $alarms))
        ->toThrow(UnexpectedValueException::class, 'cycle');
});

test('topology suppressor rejects invalid topology format', function () {
    expect(fn () => TopologySuppressor::suppress(['R' => 'invalid_string'], []))->toThrow(InvalidArgumentException::class);
});

test('topology suppressor verifier catches invalid results', function () {
    $topology = [
        'R' => ['S1'],
        'S1' => ['L1'],
    ];
    $alarms = [
        ['id' => '1', 'node' => 'R', 'severity' => 'down'],
        ['id' => '2', 'node' => 'L1', 'severity' => 'down'],
    ];
    $base = TopologySuppressor::suppress($topology, $alarms);

    // Missing keys
    expect(fn () => TopologySuppressorVerifier::verify([], $topology))->toThrow(UnexpectedValueException::class);

    // Tampered root cause severity
    $c = $base;
    $c['alarms'][0]['severity'] = 'info';
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // Missing ticket on root cause
    $c = $base;
    $c['alarms'][0]['ticket_id'] = null;
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // False root cause that actually has a down ancestor
    $c = $base;
    $c['alarms'][1]['status'] = 'root_cause';
    $c['alarms'][1]['ticket_id'] = 'TKT-L1';
    $c['tickets'][] = [
        'ticket_id' => 'TKT-L1',
        'root_node' => 'L1',
        'alarm_id' => '2',
        'severity' => 'down',
        'suppressed_alarm_ids' => [],
    ];
    $c['root_causes_count'] = 2;
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // Suppressed alarm missing root cause node
    $c = $base;
    $c['alarms'][1]['root_cause_node'] = null;
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // Suppressed alarm references non-down root cause
    $c = $base;
    $c['alarms'][1]['root_cause_node'] = 'UnknownNode';
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // Suppressed alarm invalid path (too short)
    $c = $base;
    $c['alarms'][1]['ancestor_path'] = ['R'];
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // Suppressed alarm path start mismatch
    $c = $base;
    $c['alarms'][1]['ancestor_path'] = ['S1', 'L1'];
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // Suppressed alarm path end mismatch
    $c = $base;
    $c['alarms'][1]['ancestor_path'] = ['R', 'S1'];
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // Suppressed alarm non-existent edge in path (hop 0 -> hop 1)
    $c = $base;
    $c['alarms'][1]['ancestor_path'] = ['R', 'NonExistent', 'L1'];
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // Suppressed alarm non-existent second hop edge (hop 1 -> hop 2)
    $c = $base;
    $c['alarms'][1]['ancestor_path'] = ['R', 'S1', 'Ghost'];
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // Independent alarm with down ancestor
    $c = $base;
    $c['alarms'][1]['status'] = 'independent';
    $c['suppressed_count'] = 0;
    $c['independent_count'] = 1;
    $c['tickets'][0]['suppressed_alarm_ids'] = [];
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // Count mismatch
    $c = $base;
    $c['root_causes_count'] = 999;
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    $c = $base;
    $c['suppressed_count'] = 999;
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    $c = $base;
    $c['independent_count'] = 999;
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // Ticket count mismatch
    $c = $base;
    $c['tickets'] = [];
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // Total suppressed alarm IDs count mismatch
    $c = $base;
    $c['tickets'][0]['suppressed_alarm_ids'] = ['extra_unreal'];
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);

    // Unknown status
    $c = $base;
    $c['alarms'][0]['status'] = 'magic';
    expect(fn () => TopologySuppressorVerifier::verify($c, $topology))->toThrow(UnexpectedValueException::class);
});

test('topology suppressor benchmark: 100.000 network elements with depth 10 disupresi in < 2.0s', function () {
    $totalNodes = 100000;
    $topology = [];
    $alarms = [];

    // Root alarm down
    $alarms[] = ['id' => 'alm-root', 'node' => 'node_0', 'severity' => 'down'];

    // Generate balanced tree topology up to 100,000 nodes
    for ($i = 1; $i < $totalNodes; $i++) {
        $parent = 'node_'.intdiv($i - 1, 3);
        $child = 'node_'.$i;
        $topology[$parent][] = $child;

        if ($i % 500 === 0) {
            $alarms[] = ['id' => 'alm-'.$i, 'node' => $child, 'severity' => 'down'];
        }
    }

    $start = microtime(true);
    $result = TopologySuppressor::suppress($topology, $alarms);
    $duration = microtime(true) - $start;

    expect($duration)->toBeLessThan(2.0)
        ->and($result['root_causes_count'])->toBe(1)
        ->and($result['tickets'])->toHaveCount(1)
        ->and($result['suppressed_count'])->toBe(count($alarms) - 1)
        ->and(TopologySuppressorVerifier::verify($result, $topology))->toBeTrue();
})->group('skala');
