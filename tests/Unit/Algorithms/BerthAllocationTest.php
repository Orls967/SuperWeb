<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Scheduling\BerthAllocation;

test('vektor prompt d27-3 alokasi tambatan diskrit branch and bound eksak', function () {
    $algo = new BerthAllocation;

    $numBerths = 2;
    $vessels = [
        ['id' => 'V1', 'arrival' => 1, 'duration' => 10, 'weight' => 1],
        ['id' => 'V2', 'arrival' => 1, 'duration' => 4, 'weight' => 2],
        ['id' => 'V3', 'arrival' => 2, 'duration' => 6, 'weight' => 1],
        ['id' => 'V4', 'arrival' => 3, 'duration' => 3, 'weight' => 3],
        ['id' => 'V5', 'arrival' => 4, 'duration' => 5, 'weight' => 2],
    ];

    $result = $algo->allocate($numBerths, $vessels);

    expect($result['is_exact'])->toBeTrue()
        ->and($result['objective'])->toBe(15)
        ->and($result['certificate']['is_valid'])->toBeTrue()
        ->and($result['certificate']['no_overlap'])->toBeTrue()
        ->and($result['certificate']['valid_arrivals'])->toBeTrue();

    // Verifikasi alokasi per kapal
    expect($result['allocations']['V4']['berth'])->toBe(1)
        ->and($result['allocations']['V4']['start'])->toBe(3)
        ->and($result['allocations']['V4']['end'])->toBe(6)
        ->and($result['allocations']['V1']['berth'])->toBe(1)
        ->and($result['allocations']['V1']['start'])->toBe(6)
        ->and($result['allocations']['V1']['end'])->toBe(16)
        ->and($result['allocations']['V2']['berth'])->toBe(2)
        ->and($result['allocations']['V2']['start'])->toBe(1)
        ->and($result['allocations']['V2']['end'])->toBe(5)
        ->and($result['allocations']['V5']['berth'])->toBe(2)
        ->and($result['allocations']['V5']['start'])->toBe(5)
        ->and($result['allocations']['V5']['end'])->toBe(10)
        ->and($result['allocations']['V3']['berth'])->toBe(2)
        ->and($result['allocations']['V3']['start'])->toBe(10)
        ->and($result['allocations']['V3']['end'])->toBe(16);
});

test('sertifikat rusak ditolak dengan InvalidArgumentException', function () {
    $algo = new BerthAllocation;

    $numBerths = 2;
    $vessels = [
        ['id' => 'V1', 'arrival' => 1, 'duration' => 10, 'weight' => 1],
        ['id' => 'V2', 'arrival' => 1, 'duration' => 4, 'weight' => 2],
    ];

    $result = $algo->allocate($numBerths, $vessels);

    // Rusak sertifikat dengan membuat overlap di tambatan 1
    $corruptedResult = $result;
    $corruptedResult['berth_schedules'][1] = [
        ['vessel_id' => 'V1', 'start' => 1, 'end' => 10, 'duration' => 10],
        ['vessel_id' => 'V2', 'start' => 5, 'end' => 9, 'duration' => 4], // Overlap!
    ];

    expect(fn () => $algo->verifyCertificate($corruptedResult, $numBerths, $vessels))
        ->toThrow(InvalidArgumentException::class, 'Tumpang tindih jadwal terdeteksi');
});
