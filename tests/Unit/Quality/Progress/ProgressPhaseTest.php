<?php

declare(strict_types=1);

use App\Quality\Progress\ProgressPhase;

it('determines if phase is Fase R correctly', function (): void {
    $phaseR = new ProgressPhase('R0', 'Fase R0', '🔵', '🔵 SIAP VERIFIKASI', '2026-10-14');
    $phaseOther = new ProgressPhase('SETUP', 'Setup', '✅', '✅ SELESAI', '2026-10-10');

    expect($phaseR->isFaseR())->toBeTrue()
        ->and($phaseOther->isFaseR())->toBeFalse();
});

it('determines if phase is verified based on check icon', function (): void {
    $verified = new ProgressPhase('R0', 'Fase R0', '✅', '✅ TERVERIFIKASI', '2026-10-14');
    $unverified = new ProgressPhase('R0', 'Fase R0', '🔵', '🔵 SIAP VERIFIKASI', '2026-10-14');

    expect($verified->isVerified())->toBeTrue()
        ->and($unverified->isVerified())->toBeFalse();
});

it('checks if status date changed after cutoff date', function (): void {
    $noDate = new ProgressPhase('R0', 'Fase R0', '🔵', '🔵', null);
    $afterCutoff = new ProgressPhase('R0', 'Fase R0', '🔵', '🔵', '2026-10-14');
    $onCutoff = new ProgressPhase('R0', 'Fase R0', '🔵', '🔵', '2026-10-10');
    $beforeCutoff = new ProgressPhase('R0', 'Fase R0', '🔵', '🔵', '2026-10-09');

    expect($noDate->isStatusChangedAfter('2026-10-10'))->toBeFalse()
        ->and($afterCutoff->isStatusChangedAfter('2026-10-10'))->toBeTrue()
        ->and($onCutoff->isStatusChangedAfter('2026-10-10'))->toBeFalse()
        ->and($beforeCutoff->isStatusChangedAfter('2026-10-10'))->toBeFalse();
});

it('extracts open critical minus IDs while ignoring closed and redirected ones', function (): void {
    $minusEntries = [
        [
            'id' => 'M-R0-01',
            'item' => 'R0.1',
            'minus' => 'Desc 1',
            'dampak' => 'Impact 1',
            'prioritas' => 'P0',
            'rencana' => 'Fix in round 2',
            'is_closed' => false,
        ],
        [
            'id' => 'M-R0-02',
            'item' => 'R0.2',
            'minus' => 'Desc 2',
            'dampak' => 'Impact 2',
            'prioritas' => 'p1', // test case-insensitivity and trim
            'rencana' => 'Fix soon',
            'is_closed' => false,
        ],
        [
            'id' => 'M-R0-03',
            'item' => 'R0.3',
            'minus' => 'Desc 3',
            'dampak' => 'Impact 3',
            'prioritas' => 'P0',
            'rencana' => 'Done',
            'is_closed' => true, // closed -> ignored
        ],
        [
            'id' => 'M-R0-04',
            'item' => 'R0.4',
            'minus' => 'Desc 4',
            'dampak' => 'Impact 4',
            'prioritas' => 'P1',
            'rencana' => '↪️ dialihkan ke R1.2 (DECISIONS.md#2026-10-14-p9-minus-dialihkan)', // redirected -> ignored
            'is_closed' => false,
        ],
        [
            'id' => 'M-R0-05',
            'item' => 'R0.5',
            'minus' => 'Desc 5',
            'dampak' => 'Impact 5',
            'prioritas' => 'P2', // non-critical -> ignored
            'rencana' => 'Later',
            'is_closed' => false,
        ],
        [
            'item' => 'R0.6',
            'minus' => 'Missing ID entry',
            'dampak' => 'Impact',
            'prioritas' => 'P0',
            'rencana' => 'Fix',
            'is_closed' => false,
        ],
    ];

    $phase = new ProgressPhase('R0', 'Fase R0', '🔵', '🔵', '2026-10-14', [], $minusEntries);
    $critical = $phase->openCriticalMinusIds();

    expect($critical)->toBe(['M-R0-01', 'M-R0-02', 'unknown']);
});
