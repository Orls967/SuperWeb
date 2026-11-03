<?php

declare(strict_types=1);

namespace Tests\Unit\Numerics;

use InvalidArgumentException;
use Modules\Shared\Domain\Numerics\IntervalIndex;
use Modules\Shared\Domain\Numerics\IntervalIndexVerifier;
use UnexpectedValueException;

test('interval index exact vector: L1 exclusive conflicts with overlapping L2 non-exclusive, while L3 and L4 do not conflict', function () {
    $intervals = [
        [
            'id' => 'L1',
            'territory' => 'ID',
            'right_type' => 'master',
            'start' => '2026-01-01',
            'end' => '2026-06-30',
            'exclusive' => true,
        ],
        [
            'id' => 'L2',
            'territory' => 'ID',
            'right_type' => 'master',
            'start' => '2026-06-01',
            'end' => '2026-12-31',
            'exclusive' => false,
        ],
        [
            'id' => 'L3',
            'territory' => 'ID',
            'right_type' => 'master',
            'start' => '2026-07-01',
            'end' => '2026-12-31',
            'exclusive' => false,
        ],
        [
            'id' => 'L4',
            'territory' => 'ID',
            'right_type' => 'master',
            'start' => '2026-07-01',
            'end' => '2026-12-31',
            'exclusive' => false,
        ],
    ];

    $result = IntervalIndex::detectConflicts($intervals);

    expect($result['total_intervals'])->toBe(4)
        ->and($result['has_conflict'])->toBeTrue()
        ->and($result['conflicts_count'])->toBe(1)
        ->and($result['certificate']['total_intervals'])->toBe(4)
        ->and($result['certificate']['has_conflict'])->toBeTrue()
        ->and($result['certificate']['conflicts_count'])->toBe(1)
        ->and($result['certificate']['conflicts'])->toHaveCount(1);

    $conflict = $result['conflicts'][0];
    expect($conflict['interval_id_1'])->toBe('L1')
        ->and($conflict['interval_id_2'])->toBe('L2')
        ->and($conflict['territory'])->toBe('ID')
        ->and($conflict['right_type'])->toBe('master')
        ->and($conflict['conflict_type'])->toBe('exclusive_non_exclusive')
        ->and($conflict['overlap_start'])->toBe(strtotime('2026-06-01'))
        ->and($conflict['overlap_end'])->toBe(strtotime('2026-06-30'));

    expect(IntervalIndexVerifier::verify($result, $intervals))->toBeTrue();
});

test('interval index detects exclusive-exclusive conflict with inverted IDs to check lexicographical sort', function () {
    // Z starts first, A starts second, both exclusive -> conflict pair should be [A, Z]
    $intervals = [
        ['id' => 'Z', 'territory' => 'GLOBAL', 'right_type' => 'sync', 'start' => 100, 'end' => 200, 'exclusive' => true],
        ['id' => 'A', 'territory' => 'GLOBAL', 'right_type' => 'sync', 'start' => 150, 'end' => 250, 'exclusive' => true],
    ];

    $result = IntervalIndex::detectConflicts($intervals);

    expect($result['has_conflict'])->toBeTrue()
        ->and($result['conflicts_count'])->toBe(1)
        ->and($result['conflicts'][0]['interval_id_1'])->toBe('A')
        ->and($result['conflicts'][0]['interval_id_2'])->toBe('Z')
        ->and($result['conflicts'][0]['conflict_type'])->toBe('exclusive_exclusive')
        ->and($result['conflicts'][0]['overlap_start'])->toBe(150)
        ->and($result['conflicts'][0]['overlap_end'])->toBe(200);

    expect(IntervalIndexVerifier::verify($result, $intervals))->toBeTrue();
});

test('interval index trims whitespace and sorts intervals with equal start times by end then id', function () {
    $intervals = [
        ['id' => 'I3', 'territory' => ' ID ', 'right_type' => ' master ', 'start' => 100, 'end' => 250, 'exclusive' => true],
        ['id' => 'I1', 'territory' => 'ID', 'right_type' => 'master', 'start' => 100, 'end' => 200, 'exclusive' => true],
        ['id' => 'I2', 'territory' => 'ID', 'right_type' => 'master', 'start' => 100, 'end' => 200, 'exclusive' => false],
    ];

    $result = IntervalIndex::detectConflicts($intervals);

    expect($result['indexed_intervals'][0]['territory'])->toBe('ID')
        ->and($result['indexed_intervals'][0]['right_type'])->toBe('master')
        ->and($result['has_conflict'])->toBeTrue()
        ->and($result['conflicts_count'])->toBe(3); // I1-I2, I1-I3, I2-I3

    expect(IntervalIndexVerifier::verify($result, $intervals))->toBeTrue();
});

test('interval index sweep-line prunes expired intervals correctly', function () {
    // A: [100, 200) exclusive
    // B: [150, 250) non-exclusive -> conflicts with A
    // C: [220, 300) exclusive -> conflicts with B (since 220 < 250 and C exclusive), but NOT A (since A ended at 200)
    $intervals = [
        ['id' => 'A', 'territory' => 'ID', 'right_type' => 'sync', 'start' => 100, 'end' => 200, 'exclusive' => true],
        ['id' => 'B', 'territory' => 'ID', 'right_type' => 'sync', 'start' => 150, 'end' => 250, 'exclusive' => false],
        ['id' => 'C', 'territory' => 'ID', 'right_type' => 'sync', 'start' => 220, 'end' => 300, 'exclusive' => true],
    ];

    $result = IntervalIndex::detectConflicts($intervals);

    expect($result['conflicts_count'])->toBe(2);

    $conflicts = $result['conflicts'];
    // Conflict 1: A vs B
    expect($conflicts[0]['interval_id_1'])->toBe('A')
        ->and($conflicts[0]['interval_id_2'])->toBe('B')
        ->and($conflicts[0]['overlap_start'])->toBe(150)
        ->and($conflicts[0]['overlap_end'])->toBe(200);

    // Conflict 2: B vs C
    expect($conflicts[1]['interval_id_1'])->toBe('B')
        ->and($conflicts[1]['interval_id_2'])->toBe('C')
        ->and($conflicts[1]['overlap_start'])->toBe(220)
        ->and($conflicts[1]['overlap_end'])->toBe(250);

    expect(IntervalIndexVerifier::verify($result, $intervals))->toBeTrue();
});

test('interval index allows adjacent half-open intervals touching at boundary without conflict', function () {
    $intervals = [
        ['id' => 'A', 'territory' => 'ID', 'right_type' => 'master', 'start' => 100, 'end' => 200, 'exclusive' => true],
        ['id' => 'B', 'territory' => 'ID', 'right_type' => 'master', 'start' => 200, 'end' => 300, 'exclusive' => true],
    ];

    $result = IntervalIndex::detectConflicts($intervals);

    expect($result['has_conflict'])->toBeFalse()
        ->and($result['conflicts_count'])->toBe(0)
        ->and($result['conflicts'])->toBeEmpty();

    expect(IntervalIndexVerifier::verify($result, $intervals))->toBeTrue();
});

test('interval index separates conflicts by territory and right type', function () {
    $intervals = [
        ['id' => 'A', 'territory' => 'ID', 'right_type' => 'master', 'start' => 100, 'end' => 200, 'exclusive' => true],
        ['id' => 'B', 'territory' => 'MY', 'right_type' => 'master', 'start' => 100, 'end' => 200, 'exclusive' => true],
        ['id' => 'C', 'territory' => 'ID', 'right_type' => 'publishing', 'start' => 100, 'end' => 200, 'exclusive' => true],
    ];

    $result = IntervalIndex::detectConflicts($intervals);

    expect($result['has_conflict'])->toBeFalse()
        ->and($result['conflicts_count'])->toBe(0);

    expect(IntervalIndexVerifier::verify($result, $intervals))->toBeTrue();
});

test('interval index handles integer interval IDs and multiple conflicts ordered deterministically', function () {
    $intervals = [
        ['id' => 20, 'territory' => 'ID', 'right_type' => 'master', 'start' => 100, 'end' => 300, 'exclusive' => true],
        ['id' => 10, 'territory' => 'ID', 'right_type' => 'master', 'start' => 150, 'end' => 250, 'exclusive' => true],
        ['id' => 30, 'territory' => 'ID', 'right_type' => 'master', 'start' => 200, 'end' => 350, 'exclusive' => true],
    ];

    $result = IntervalIndex::detectConflicts($intervals);

    expect($result['conflicts_count'])->toBe(3);
    expect($result['conflicts'][0]['interval_id_1'])->toBe(10)
        ->and($result['conflicts'][0]['interval_id_2'])->toBe(20)
        ->and($result['conflicts'][1]['interval_id_1'])->toBe(10)
        ->and($result['conflicts'][1]['interval_id_2'])->toBe(30)
        ->and($result['conflicts'][2]['interval_id_1'])->toBe(20)
        ->and($result['conflicts'][2]['interval_id_2'])->toBe(30);

    expect(IntervalIndexVerifier::verify($result, $intervals))->toBeTrue();
});

test('interval index validates parameter boundaries', function () {
    expect(fn () => IntervalIndex::detectConflicts([['id' => 'X']]))->toThrow(InvalidArgumentException::class);
    expect(fn () => IntervalIndex::detectConflicts([['id' => 'X', 'territory' => 'ID', 'right_type' => 'm', 'start' => 200, 'end' => 100, 'exclusive' => true]]))->toThrow(InvalidArgumentException::class);
    expect(fn () => IntervalIndex::detectConflicts([['id' => 'X', 'territory' => 'ID', 'right_type' => 'm', 'start' => 100, 'end' => 100, 'exclusive' => true]]))->toThrow(InvalidArgumentException::class);
    expect(fn () => IntervalIndex::detectConflicts([['id' => 'X', 'territory' => 'ID', 'right_type' => 'm', 'start' => 'not-a-date', 'end' => 'not-a-date-2', 'exclusive' => true]]))->toThrow(InvalidArgumentException::class);
    expect(IntervalIndex::normalizeTimestamp(12345))->toBe(12345);
});

test('interval index verifier catches tampered results', function () {
    $intervals = [
        ['id' => 'L1', 'territory' => 'ID', 'right_type' => 'master', 'start' => 100, 'end' => 200, 'exclusive' => true],
        ['id' => 'L2', 'territory' => 'ID', 'right_type' => 'master', 'start' => 150, 'end' => 250, 'exclusive' => false],
    ];
    $base = IntervalIndex::detectConflicts($intervals);

    // Missing keys
    expect(fn () => IntervalIndexVerifier::verify([], $intervals))->toThrow(UnexpectedValueException::class);

    // Total intervals mismatch
    $c = $base;
    $c['total_intervals'] = 99;
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // Duplicate interval ID in indexed
    $c = $base;
    $c['indexed_intervals'][] = $base['indexed_intervals'][0];
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // Self conflict
    $c = $base;
    $c['conflicts'][0]['interval_id_2'] = 'L1';
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // Unknown interval ID 1
    $c = $base;
    $c['conflicts'][0]['interval_id_1'] = 'Ghost';
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // Unknown interval ID 2
    $c = $base;
    $c['conflicts'][0]['interval_id_2'] = 'Ghost';
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // Territory mismatch int1
    $c = $base;
    $c['indexed_intervals'][0]['territory'] = 'US';
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // Territory mismatch int2
    $c = $base;
    $c['indexed_intervals'][1]['territory'] = 'US';
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // Right type mismatch int1
    $c = $base;
    $c['indexed_intervals'][0]['right_type'] = 'sync';
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // Right type mismatch int2
    $c = $base;
    $c['indexed_intervals'][1]['right_type'] = 'sync';
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // Overlap boundary mismatch
    $c = $base;
    $c['conflicts'][0]['overlap_start'] = 100;
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    $c = $base;
    $c['conflicts'][0]['overlap_end'] = 999;
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // Non-overlapping interval falsely reported
    $c = $base;
    $c['indexed_intervals'][1]['start'] = 300;
    $c['indexed_intervals'][1]['end'] = 400;
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // Non-exclusive false conflict
    $c = $base;
    $c['indexed_intervals'][0]['exclusive'] = false;
    $c['indexed_intervals'][1]['exclusive'] = false;
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // Conflict type mismatch
    $c = $base;
    $c['conflicts'][0]['conflict_type'] = 'exclusive_exclusive';
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // Inverted pair duplicate check
    $c = $base;
    $inverted = $base['conflicts'][0];
    $inverted['interval_id_1'] = $base['conflicts'][0]['interval_id_2'];
    $inverted['interval_id_2'] = $base['conflicts'][0]['interval_id_1'];
    $c['conflicts'][] = $inverted;
    $c['conflicts_count'] = 2;
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class, 'Duplicate conflict');

    // Duplicate conflict reported
    $c = $base;
    $c['conflicts'][] = $base['conflicts'][0];
    $c['conflicts_count'] = 2;
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class, 'Duplicate conflict');

    // Conflicts count mismatch
    $c = $base;
    $c['conflicts_count'] = 999;
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);

    // has_conflict boolean mismatch
    $c = $base;
    $c['has_conflict'] = false;
    expect(fn () => IntervalIndexVerifier::verify($c, $intervals))->toThrow(UnexpectedValueException::class);
});

test('interval index benchmark: 50.000 intervals checked in < 1.0s', function () {
    $intervals = [];
    for ($i = 0; $i < 50000; $i++) {
        $start = $i * 100;
        $intervals[] = [
            'id' => 'lic-'.$i,
            'territory' => 'TERR_'.($i % 10),
            'right_type' => 'RIGHT_'.($i % 5),
            'start' => $start,
            'end' => $start + 80,
            'exclusive' => ($i % 2 === 0),
        ];
    }

    $start = microtime(true);
    $result = IntervalIndex::detectConflicts($intervals);
    $duration = microtime(true) - $start;

    expect($duration)->toBeLessThan(1.0)
        ->and($result['total_intervals'])->toBe(50000)
        ->and($result['has_conflict'])->toBeFalse()
        ->and(IntervalIndexVerifier::verify($result, $intervals))->toBeTrue();
})->group('skala');
