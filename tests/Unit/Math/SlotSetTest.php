<?php

declare(strict_types=1);

namespace Tests\Unit\Math;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\Shared\Domain\Math\SlotSet;

covers(SlotSet::class);

test('it rejects invalid time and interval inputs', function () {
    expect(fn () => SlotSet::parseTime(-1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SlotSet::parseTime('25:00'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SlotSet::parseTime('12:60'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SlotSet::parseTime('invalid-time'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SlotSet::formatMinute(-5))->toThrow(InvalidArgumentException::class);

    // Unaligned slot bounds
    expect(fn () => SlotSet::intervalToSlots('09:05', '10:30'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SlotSet::intervalToSlots('09:00', '10:35'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SlotSet::intervalToSlots('10:00', '09:00'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SlotSet::intervalToSlots('09:00', '09:00'))->toThrow(InvalidArgumentException::class);

    // Overlap validation
    expect(fn () => SlotSet::overlaps('10:00', '09:00', '10:00', '11:00'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SlotSet::overlaps('09:00', '10:00', '11:00', '10:00'))->toThrow(InvalidArgumentException::class);

    // Adjacency validation
    expect(fn () => SlotSet::isAdjacent('10:00', '09:00', '10:00', '11:00'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SlotSet::isAdjacent('09:00', '10:00', '11:00', '10:00'))->toThrow(InvalidArgumentException::class);
});

test('it handles DateTimeInterface parsing', function () {
    $dt = new DateTimeImmutable('2026-10-31 09:00:00 UTC');
    $expectedMin = intdiv($dt->getTimestamp(), 60);
    expect(SlotSet::parseTime($dt))->toBe($expectedMin);

    $formatted = SlotSet::parseTime('2026-10-31 09:00:00');
    expect($formatted)->toBe(intdiv(strtotime('2026-10-31 09:00:00'), 60));
});

test('it matches required vector: 09:00-10:30 = 6 slots, 10:00-11:00 overlaps, 10:30-11:00 adjacent', function () {
    // 1. 09:00–10:30 = 6 slot
    $slots = SlotSet::intervalToSlots('09:00', '10:30');
    expect($slots)->toBe([36, 37, 38, 39, 40, 41])
        ->and(count($slots))->toBe(6);

    // 2. 10:00–11:00 bertumpuk (overlaps with 09:00-10:30)
    expect(SlotSet::overlaps('09:00', '10:30', '10:00', '11:00'))->toBeTrue()
        ->and(SlotSet::overlaps('10:00', '11:00', '09:00', '10:30'))->toBeTrue();

    // 3. 10:30–11:00 bersebelahan (tidak bertumpuk)
    expect(SlotSet::overlaps('09:00', '10:30', '10:30', '11:00'))->toBeFalse()
        ->and(SlotSet::isAdjacent('09:00', '10:30', '10:30', '11:00'))->toBeTrue()
        ->and(SlotSet::isAdjacent('10:30', '11:00', '09:00', '10:30'))->toBeTrue();
});

test('it converts slots back to contiguous intervals', function () {
    expect(SlotSet::slotsToIntervals([]))->toBe([])
        ->and(SlotSet::slotsToTimeIntervals([]))->toBe([]);

    // Single run
    $slots = [36, 37, 38, 39, 40, 41];
    expect(SlotSet::slotsToTimeIntervals($slots))->toBe([
        ['start' => '09:00', 'end' => '10:30'],
    ]);

    // Multiple non-contiguous runs with out-of-order slots and duplicates
    $mixedSlots = [41, 37, 36, 44, 45, 38, 39, 40, 44, 36];
    expect(SlotSet::slotsToTimeIntervals($mixedSlots))->toBe([
        ['start' => '09:00', 'end' => '10:30'],
        ['start' => '11:00', 'end' => '11:30'],
    ]);

    expect(SlotSet::slotsToIntervals($mixedSlots))->toBe([
        ['start' => 540, 'end' => 630],
        ['start' => 660, 'end' => 690],
    ]);
});

test('it computes and merges free slots correctly', function () {
    $occupied = [
        ['start' => '09:00', 'end' => '10:30'],
        ['start' => '11:00', 'end' => '11:30'],
    ];

    $free = SlotSet::mergeFreeSlots('08:00', '12:00', $occupied);

    expect($free)->toBe([
        ['start' => '08:00', 'end' => '09:00'],
        ['start' => '10:30', 'end' => '11:00'],
        ['start' => '11:30', 'end' => '12:00'],
    ]);
});

test('it performs SlotSet instance algebra operations', function () {
    $setA = SlotSet::fromInterval('09:00', '10:30'); // [36..41]
    $setB = SlotSet::fromInterval('10:00', '11:00'); // [40..43]
    $setC = SlotSet::fromInterval('10:30', '11:00'); // [42..43]

    expect($setA->count())->toBe(6)
        ->and($setA->isEmpty())->toBeFalse()
        ->and($setA->containsSlot(36))->toBeTrue()
        ->and($setA->containsSlot(42))->toBeFalse()
        ->and($setA->containsTime('09:15'))->toBeTrue()
        ->and($setA->containsTime('10:30'))->toBeFalse() // Half-open!
        ->and($setA->overlapsWith($setB))->toBeTrue()
        ->and($setA->overlapsWith($setC))->toBeFalse();

    // Union
    $union = $setA->union($setB);
    expect($union->toTimeIntervals())->toBe([
        ['start' => '09:00', 'end' => '11:00'],
    ]);

    // Intersection
    $intersect = $setA->intersect($setB);
    expect($intersect->toTimeIntervals())->toBe([
        ['start' => '10:00', 'end' => '10:30'],
    ]);

    // Difference
    $diff = $setA->diff($setB);
    expect($diff->toTimeIntervals())->toBe([
        ['start' => '09:00', 'end' => '10:00'],
    ]);

    // Invert
    $inverted = $setA->invert('08:00', '11:00');
    expect($inverted->toTimeIntervals())->toBe([
        ['start' => '08:00', 'end' => '09:00'],
        ['start' => '10:30', 'end' => '11:00'],
    ]);

    // Add and remove slot
    $mod = $setA->addSlot(50)->removeSlot(36);
    expect($mod->containsSlot(50))->toBeTrue()
        ->and($mod->containsSlot(36))->toBeFalse();

    // Add and remove interval
    $mod2 = $setA->addInterval('11:00', '12:00')->removeInterval('09:30', '10:00');
    expect($mod2->toTimeIntervals())->toBe([
        ['start' => '09:00', 'end' => '09:30'],
        ['start' => '10:00', 'end' => '10:30'],
        ['start' => '11:00', 'end' => '12:00'],
    ]);

    $empty = new SlotSet;
    expect($empty->isEmpty())->toBeTrue()
        ->and($empty->slots())->toBe([]);

    $multi = SlotSet::fromIntervals([
        ['start' => '08:00', 'end' => '09:00'],
        ['start' => '10:00', 'end' => '11:00'],
    ]);
    expect($multi->count())->toBe(8);
});

test('it matches minute-by-minute oracle property', function () {
    // Brute force minute-by-minute oracle
    mt_srand(4242);

    for ($iter = 0; $iter < 100; $iter++) {
        $oracle = array_fill(0, 1440, false);
        $numIntervals = mt_rand(1, 8);
        $intervals = [];

        for ($i = 0; $i < $numIntervals; $i++) {
            $startSlot = mt_rand(0, 90);
            $duration = mt_rand(1, 6);
            $endSlot = $startSlot + $duration;

            $startMin = $startSlot * 15;
            $endMin = $endSlot * 15;

            $intervals[] = [
                'start' => SlotSet::formatMinute($startMin),
                'end' => SlotSet::formatMinute($endMin),
            ];

            for ($m = $startMin; $m < $endMin; $m++) {
                $oracle[$m] = true;
            }
        }

        // Test SlotSet representation
        $slotSet = SlotSet::fromIntervals($intervals);

        // Every minute in [0, 1440):
        for ($m = 0; $m < 1440; $m++) {
            $slot = intdiv($m, 15);
            if ($oracle[$m]) {
                // If minute is occupied, its slot MUST be in slotSet
                expect($slotSet->containsSlot($slot))->toBeTrue();
            }
        }

        // Free slots within 00:00 - 24:00 (0 - 1440)
        $freeIntervals = SlotSet::freeIntervals('00:00', '24:00', $intervals);
        foreach ($freeIntervals as $free) {
            $fStart = SlotSet::parseTime($free['start']);
            $fEnd = SlotSet::parseTime($free['end']);
            for ($m = $fStart; $m < $fEnd; $m++) {
                // Free interval minutes MUST be false in oracle
                expect($oracle[$m])->toBeFalse();
            }
        }
    }
});

test('it scales efficiently for large numbers of intervals', function () {
    $occupied = [];
    for ($i = 0; $i < 40; $i++) {
        $startSlot = $i * 2 + 1;
        $occupied[] = [
            'start' => $startSlot * 15,
            'end' => ($startSlot + 1) * 15,
        ];
    }

    $free = SlotSet::freeSlots(0, 100 * 15, $occupied);
    expect(count($free))->toBe(60);
})->group('skala');
