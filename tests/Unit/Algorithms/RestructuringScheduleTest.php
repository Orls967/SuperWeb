<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Islamic\RestructuringSchedule;
use Modules\Shared\Domain\Algorithms\Islamic\RestructuringScheduleCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Islamic\RestructuringScheduleResult;

test('vektor algoritma restrukturisasi syariah bebas riba dengan margin haircut', function () {
    // Skenario: Pembiayaan bermasalah pokok Rp 600.000.000, margin sisa Rp 60.000.000
    // Haircut margin 50% (5000 bps) -> revised margin Rp 30.000.000
    // Tenor baru 12 bulan, grace period 2 bulan (hanya grace pokok & margin, cicilan mulai bulan 3 s/d 12 = 10 bulan)
    $res = RestructuringSchedule::calculate(
        financingRef: 'FIN-MUR-2026-9901',
        originalOutstandingPrincipalMinor: 600_000_000,
        originalRemainingMarginMinor: 60_000_000,
        marginHaircutBps: 5000,
        newTenorMonths: 12,
        gracePeriodMonths: 2,
        restructuringFeeMinor: 0
    );

    expect($res->revisedPrincipalMinor)->toBe(600_000_000)
        ->and($res->revisedMarginMinor)->toBe(30_000_000)
        ->and($res->totalRestructuredDebtMinor)->toBe(630_000_000)
        ->and($res->monthlyPrincipalInstallmentMinor)->toBe(60_000_000)
        ->and($res->monthlyMarginInstallmentMinor)->toBe(3_000_000)
        ->and($res->monthlyTotalInstallmentMinor)->toBe(63_000_000)
        ->and($res->scheduleTable[0]['is_grace'])->toBeTrue()
        ->and($res->scheduleTable[0]['installment_minor'])->toBe(0)
        ->and($res->scheduleTable[1]['is_grace'])->toBeTrue()
        ->and($res->scheduleTable[2]['is_grace'])->toBeFalse()
        ->and($res->scheduleTable[2]['installment_minor'])->toBe(63_000_000)
        ->and($res->scheduleTable[11]['remaining_principal_minor'])->toBe(0)
        ->and($res->scheduleTable[11]['remaining_margin_minor'])->toBe(0);

    // Verifikasi sertifikat sah
    expect(RestructuringScheduleCertificateVerifier::verify($res))->toBeTrue();
});

test('sertifikat rusak pada restrukturisasi memicu exception anti riba', function () {
    $res = RestructuringSchedule::calculate(
        financingRef: 'FIN-MUR-2026-9902',
        originalOutstandingPrincipalMinor: 100_000_000,
        originalRemainingMarginMinor: 10_000_000,
        marginHaircutBps: 0,
        newTenorMonths: 10,
        gracePeriodMonths: 0
    );

    // Sabotase: naikkan revised margin melebihi margin lama (riba)
    $tampered = new RestructuringScheduleResult(
        financingRef: $res->financingRef,
        originalPrincipalMinor: $res->originalPrincipalMinor,
        originalMarginMinor: $res->originalMarginMinor,
        marginHaircutBps: $res->marginHaircutBps,
        revisedPrincipalMinor: $res->revisedPrincipalMinor,
        revisedMarginMinor: 15_000_000, // Tambahan margin ilegal Rp 5jt
        totalRestructuredDebtMinor: 115_000_000,
        newTenorMonths: $res->newTenorMonths,
        gracePeriodMonths: $res->gracePeriodMonths,
        monthlyPrincipalInstallmentMinor: $res->monthlyPrincipalInstallmentMinor,
        monthlyMarginInstallmentMinor: $res->monthlyMarginInstallmentMinor,
        monthlyTotalInstallmentMinor: $res->monthlyTotalInstallmentMinor,
        scheduleTable: $res->scheduleTable,
        certificateHash: $res->certificateHash
    );

    expect(fn () => RestructuringScheduleCertificateVerifier::verify($tampered))
        ->toThrow(RuntimeException::class, 'Riba violation: Revised margin exceeds original margin.');
});
