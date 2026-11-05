<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Islamic\NpfClassifier;
use Modules\Shared\Domain\Algorithms\Islamic\NpfClassifierCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Islamic\NpfClassifierResult;

test('vektor algoritma klasifikasi npf 1 sampai 5 dan cadangan ckpn', function () {
    $contracts = [
        ['contract_ref' => 'FIN-001', 'dpd' => 0, 'outstanding_principal_minor' => 1_000_000_000],   // Kol 1 (1%) -> 10.000.000
        ['contract_ref' => 'FIN-002', 'dpd' => 45, 'outstanding_principal_minor' => 500_000_000],   // Kol 2 (5%) -> 25.000.000
        ['contract_ref' => 'FIN-003', 'dpd' => 100, 'outstanding_principal_minor' => 200_000_000],  // Kol 3 (15%) -> 30.000.000 (NPF)
        ['contract_ref' => 'FIN-004', 'dpd' => 150, 'outstanding_principal_minor' => 100_000_000],  // Kol 4 (50%) -> 50.000.000 (NPF)
        ['contract_ref' => 'FIN-005', 'dpd' => 210, 'outstanding_principal_minor' => 100_000_000],  // Kol 5 (100%) -> 100.000.000 (NPF)
    ];

    // Total Principal: 1.900.000.000
    // Performing: 1.500.000.000
    // Non-Performing: 400.000.000 (FIN-003, 004, 005)
    // NPF Gross Ratio: 400.000.000 / 1.900.000.000 = 21,05% (2105 bps) -> isCompliant = false (> 500 bps)
    // Total Provision: 10 + 25 + 30 + 50 + 100 = 215.000.000
    // Non-performing Provision: 30 + 50 + 100 = 180.000.000
    // Net NPF Principal: 400.000.000 - 180.000.000 = 220.000.000
    // NPF Net Ratio: 220.000.000 / 1.900.000.000 = 11,58% (1158 bps)

    $res = NpfClassifier::classifyPortfolio($contracts);

    expect($res->totalContracts)->toBe(5)
        ->and($res->totalPortfolioPrincipalMinor)->toBe(1_900_000_000)
        ->and($res->performingPrincipalMinor)->toBe(1_500_000_000)
        ->and($res->nonPerformingPrincipalMinor)->toBe(400_000_000)
        ->and($res->totalRequiredProvisionMinor)->toBe(215_000_000)
        ->and($res->npfGrossRatioBps)->toBe(2105)
        ->and($res->npfNetRatioBps)->toBe(1158)
        ->and($res->isCompliantWithPrudentialLimit)->toBeFalse()
        ->and($res->bucketSummary[1]['count'])->toBe(1)
        ->and($res->bucketSummary[5]['count'])->toBe(1);

    expect(NpfClassifierCertificateVerifier::verify($res))->toBeTrue();
});

test('sertifikat rusak pada npf classifier memicu exception integritas', function () {
    $contracts = [
        ['contract_ref' => 'FIN-001', 'dpd' => 0, 'outstanding_principal_minor' => 100_000_000],
    ];

    $res = NpfClassifier::classifyPortfolio($contracts);

    // Sabotase: kurangi totalRequiredProvisionMinor secara sepihak
    $tampered = new NpfClassifierResult(
        totalContracts: $res->totalContracts,
        totalPortfolioPrincipalMinor: $res->totalPortfolioPrincipalMinor,
        performingPrincipalMinor: $res->performingPrincipalMinor,
        nonPerformingPrincipalMinor: $res->nonPerformingPrincipalMinor,
        totalRequiredProvisionMinor: 0, // Dikecilkan agar tampak aman
        npfGrossRatioBps: $res->npfGrossRatioBps,
        npfNetRatioBps: $res->npfNetRatioBps,
        isCompliantWithPrudentialLimit: $res->isCompliantWithPrudentialLimit,
        bucketSummary: $res->bucketSummary,
        classifiedItems: $res->classifiedItems,
        certificateHash: $res->certificateHash
    );

    expect(fn () => NpfClassifierCertificateVerifier::verify($tampered))
        ->toThrow(RuntimeException::class, 'Integrity violation: Sum of bucket provisions does not match total required provision.');
});
