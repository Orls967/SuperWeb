<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Reinsurance\TakafulSurplus;
use Modules\Shared\Domain\Algorithms\Reinsurance\TakafulSurplusCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Reinsurance\TakafulSurplusResult;

test('vektor algoritma A8 menghitung surplus tabarru dan memverifikasi sertifikat', function () {
    $calculator = new TakafulSurplus;
    $verifier = new TakafulSurplusCertificateVerifier;

    // Vektor uji:
    // Kontribusi bersih: Rp 10.000.000.000 (1.000.000.000.000 sen)
    // Klaim terjadi: Rp 6.000.000.000 (600.000.000.000 sen)
    // Cadangan teknis: Rp 1.000.000.000 (100.000.000.000 sen)
    // Gross Surplus: Rp 3.000.000.000 (300.000.000.000 sen)
    // Nisbah: Peserta 50% (1,5M), Cadangan Tabarru' 30% (900jt), Mudharabah Pengelola 20% (600jt)
    $res = $calculator->calculate(
        netContributionIdr: 1000000000000,
        netIncurredClaimsIdr: 600000000000,
        technicalReserveIdr: 100000000000,
        participantShareBps: 5000,
        contingencyReserveBps: 3000,
        operatorMudharabahBps: 2000,
    );

    expect($res->grossSurplusIdr)->toBe(300000000000)
        ->and($res->participantSurplusShareIdr)->toBe(150000000000)
        ->and($res->contingencyReserveShareIdr)->toBe(90000000000)
        ->and($res->mudharabahOperatorShareIdr)->toBe(60000000000)
        ->and($res->allocations['participants'] + $res->allocations['contingency'] + $res->allocations['operator'])
        ->toBe(300000000000);

    expect($verifier->verify($res))->toBeTrue();
});

test('sertifikat surplus takaful yang dimanipulasi melempar runtime exception', function () {
    $calculator = new TakafulSurplus;
    $verifier = new TakafulSurplusCertificateVerifier;

    $res = $calculator->calculate(
        netContributionIdr: 1000000000,
        netIncurredClaimsIdr: 500000000,
        technicalReserveIdr: 100000000,
    );

    $corrupted = new TakafulSurplusResult(
        netContributionIdr: $res->netContributionIdr,
        netIncurredClaimsIdr: $res->netIncurredClaimsIdr,
        technicalReserveIdr: $res->technicalReserveIdr,
        grossSurplusIdr: $res->grossSurplusIdr,
        participantSurplusShareIdr: $res->participantSurplusShareIdr + 5000,
        contingencyReserveShareIdr: $res->contingencyReserveShareIdr,
        mudharabahOperatorShareIdr: $res->mudharabahOperatorShareIdr,
        allocations: $res->allocations,
        certificate: $res->certificate,
    );

    $verifier->verify($corrupted);
})->throws(RuntimeException::class);
