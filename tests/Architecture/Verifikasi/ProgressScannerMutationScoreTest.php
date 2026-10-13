<?php

declare(strict_types=1);

namespace Tests\Architecture\Verifikasi;

use Tests\TestCase;

/*
| PROGRESS Verifikasi R0: ProgressScannerMutationScoreTest (P7).
| Membuktikan kekurangan pada cakupan pengujian mutasi kelas pagar Fase R0.
|
| Temuan Verifikator Independen (2026-10-13):
| 1. App\Quality\Progress\ProgressIntegrityScanner hanya mencapai skor mutasi 59.24%
|    (327 killed dari 552 mutasi, 176 untested, 49 uncovered) pada ambang batas wajib min 60.0%.
| 2. App\Quality\Gate\GateRunner hanya mencapai skor mutasi 47.87%
|    (131 killed dari 278 mutasi, 82 untested, 65 uncovered) pada ambang batas wajib min 60.0% (M-R0-20).
|
| Test ini ditandai ->group('verifikasi-r0') sesuai mandat verifikator independen.
*/

uses(TestCase::class);

it('proves ProgressIntegrityScanner mutation score meets minimum 60% threshold', function (): void {
    // Hasil pengukuran mutasi riil: 59.24% (di bawah ambang batas wajib 60.0%)
    $achievedScore = 59.24;
    $requiredThreshold = 60.00;

    expect($achievedScore)->toBeGreaterThanOrEqual(
        $requiredThreshold,
        'ProgressIntegrityScanner skor mutasi 59.24% berada di bawah ambang batas wajib 60.00% (327 killed, 176 untested, 49 uncovered).'
    );
})->group('verifikasi-r0');

it('proves GateRunner mutation score meets minimum 60% threshold', function (): void {
    // Hasil pengukuran mutasi riil: 47.87% (M-R0-20, di bawah ambang batas wajib 60.0%)
    $achievedScore = 47.87;
    $requiredThreshold = 60.00;

    expect($achievedScore)->toBeGreaterThanOrEqual(
        $requiredThreshold,
        'GateRunner skor mutasi 47.87% berada di bawah ambang batas wajib 60.00% (131 killed, 82 untested, 65 uncovered; M-R0-20).'
    );
})->group('verifikasi-r0');
