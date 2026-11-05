<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\LifeContingency\TermLifePremium;
use Modules\Shared\Domain\Algorithms\LifeContingency\TermLifePremiumCertificateVerifier;
use Modules\Shared\Domain\Algorithms\LifeContingency\TermLifePremiumResult;

test('vektor prompt aktuaria term life menghitung premi murni tahunan dan sertifikat valid', function (): void {
    $calculator = new TermLifePremium;
    $verifier = new TermLifePremiumCertificateVerifier;

    // Tertanggung usia 35 tahun, proteksi 10 tahun, UP Rp 500.000.000, suku bunga 5% (500 bps)
    $result = $calculator->calculate(age: 35, termYears: 10, sumInsuredIdr: 500000000, interestRateBps: 500);

    expect($result)->toBeInstanceOf(TermLifePremiumResult::class);
    expect($result->sumInsuredIdr)->toBe(500000000);
    expect($result->age)->toBe(35);
    expect($result->termYears)->toBe(10);
    expect($result->netSinglePremiumIdr)->toBeGreaterThan(0);
    expect($result->annualNetPremiumIdr)->toBeGreaterThan(0);
    expect($result->netSinglePremiumIdr)->toBeGreaterThan($result->annualNetPremiumIdr);

    // Verifikasi sertifikat
    expect($verifier->verify($result))->toBeTrue();
});

test('sertifikat term life yang dimanipulasi melempar exception', function (): void {
    $calculator = new TermLifePremium;
    $verifier = new TermLifePremiumCertificateVerifier;

    $result = $calculator->calculate(age: 40, termYears: 20, sumInsuredIdr: 1000000000, interestRateBps: 500);

    // Korupsi sertifikat dengan memodifikasi nilai premi
    $corrupted = new TermLifePremiumResult(
        annualNetPremiumIdr: $result->annualNetPremiumIdr + 50000, // manipulasi
        netSinglePremiumIdr: $result->netSinglePremiumIdr,
        sumInsuredIdr: $result->sumInsuredIdr,
        age: $result->age,
        termYears: $result->termYears,
        interestRateBps: $result->interestRateBps,
        commutationTable: $result->commutationTable,
        certificateHash: $result->certificateHash
    );

    expect(fn () => $verifier->verify($corrupted))
        ->toThrow(InvalidArgumentException::class, 'Sertifikat hash kalkulasi premi Term Life tidak valid');
});
