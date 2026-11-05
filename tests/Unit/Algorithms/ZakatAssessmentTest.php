<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Islamic\ZakatAssessment;
use Modules\Shared\Domain\Algorithms\Islamic\ZakatAssessmentCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Islamic\ZakatAssessmentResult;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

test('vektor algoritma zakat assessment mal', function (): void {
    $algo = new ZakatAssessment();

    // Vektor prompt:
    // Harga emas 1.500.000/g -> nisab 127.500.000
    // Harta 200.000.000, utang 20.000.000 -> zakat 4.500.000
    $res1 = $algo->assess(
        goldPricePerGramIdr: 1_500_000,
        qualifyingWealthIdr: 200_000_000,
        dueDebtsIdr: 20_000_000,
        holdingDays: 354
    );

    expect($res1->nisabIdr)->toBe(127_500_000)
        ->and($res1->netZakatBaseIdr)->toBe(180_000_000)
        ->and($res1->isObligated)->toBeTrue()
        ->and($res1->zakatAmountIdr)->toBe(4_500_000);

    // Harta 130.000.000, utang 10.000.000 -> 0 (di bawah nisab 127.500.000)
    $res2 = $algo->assess(
        goldPricePerGramIdr: 1_500_000,
        qualifyingWealthIdr: 130_000_000,
        dueDebtsIdr: 10_000_000,
        holdingDays: 354
    );

    expect($res2->netZakatBaseIdr)->toBe(120_000_000)
        ->and($res2->reachesNisab)->toBeFalse()
        ->and($res2->isObligated)->toBeFalse()
        ->and($res2->zakatAmountIdr)->toBe(0);
});

test('sertifikat rusak pada zakat assessment memicu exception', function (): void {
    $corrupt = new ZakatAssessmentResult(
        goldPricePerGramIdr: 1_500_000,
        nisabIdr: 100_000_000, // Rusak: seharusnya 127.500.000
        qualifyingWealthIdr: 200_000_000,
        dueDebtsIdr: 20_000_000,
        netZakatBaseIdr: 180_000_000,
        holdingDays: 354,
        haulFulfilled: true,
        reachesNisab: true,
        isObligated: true,
        zakatAmountIdr: 4_500_000
    );

    (new ZakatAssessmentCertificateVerifier())->verify($corrupt);
})->throws(InvalidCertificateException::class);
