<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Islamic\GigCreditLimit;
use Modules\Shared\Domain\Algorithms\Islamic\GigCreditLimitCertificateVerifier;
use Tests\TestCase;

uses(TestCase::class);

test('vektor algoritma gig credit limit dan scoring amanah', function () {
    // 6 bulan pendapatan relatif stabil: rata-rata Rp 5.000.000 (500000000 sen)
    $incomes = [
        500000000,
        520000000,
        480000000,
        510000000,
        490000000,
        500000000,
    ];

    $res = GigCreditLimit::calculate(
        workerPartyId: 'GIG-WRK-001',
        monthlyIncomes: $incomes,
        amanahScore: 90, // Sangat amanah
        maxDsrBps: 3000, // 30%
        tenorMonths: 12
    );

    expect($res->averageMonthlyIncome)->toBe(500000000)
        ->and($res->adjustedDsrBps)->toBe(3000)
        ->and($res->riskGrade)->toBe('LOW_RISK')
        ->and($res->isEligible)->toBeTrue();

    // Kapasitas cicilan = 5.000.000 * 30% = 1.500.000 (150000000 sen). Faktor amanah (90%) = 1.350.000 (135000000 sen)
    expect($res->maxMonthlyInstallment)->toBe(135000000)
        ->and($res->maxCreditLimit)->toBe(135000000 * 12);

    expect(GigCreditLimitCertificateVerifier::verify($res->certificate))->toBeTrue();
});

test('sertifikat rusak pada gig credit limit memicu exception', function () {
    $incomes = [400000000, 450000000, 380000000];
    $res = GigCreditLimit::calculate(
        workerPartyId: 'GIG-WRK-002',
        monthlyIncomes: $incomes,
        amanahScore: 75
    );

    $corruptedCertificate = $res->certificate;
    $corruptedCertificate['hash'] = 'corrupted_hash_gig';

    expect(fn () => GigCreditLimitCertificateVerifier::verify($corruptedCertificate))
        ->toThrow(RuntimeException::class);
});
