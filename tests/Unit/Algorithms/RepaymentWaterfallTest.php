<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Islamic\RepaymentWaterfall;
use Modules\Shared\Domain\Algorithms\Islamic\RepaymentWaterfallCertificateVerifier;
use Tests\TestCase;

uses(TestCase::class);

test('vektor algoritma repayment waterfall pembiayaan mikro berjenjang', function () {
    // Skenario 1: Pembayaran pas margin + pokok, tanpa ta'zir
    $res1 = RepaymentWaterfall::allocate(
        paymentAmount: 130000000, // Rp 1.300.000
        dueMargin: 30000000,      // Rp 300.000
        duePrincipal: 100000000,  // Rp 1.000.000
        tazirDailyRateBps: 0,
        daysOverdue: 0
    );

    expect($res1->allocatedMargin)->toBe(30000000)
        ->and($res1->allocatedPrincipal)->toBe(100000000)
        ->and($res1->allocatedTazir)->toBe(0)
        ->and($res1->excessDeposit)->toBe(0)
        ->and($res1->unpaidMargin)->toBe(0)
        ->and($res1->unpaidPrincipal)->toBe(0)
        ->and($res1->isFullySettled)->toBeTrue();

    expect(RepaymentWaterfallCertificateVerifier::verify($res1->certificate))->toBeTrue();

    // Skenario 2: Pembayaran parsial hanya menutup margin dan sebagian pokok
    $res2 = RepaymentWaterfall::allocate(
        paymentAmount: 50000000,  // Rp 500.000
        dueMargin: 30000000,      // Rp 300.000
        duePrincipal: 100000000,  // Rp 1.000.000
        tazirDailyRateBps: 0,
        daysOverdue: 0
    );

    expect($res2->allocatedMargin)->toBe(30000000)
        ->and($res2->allocatedPrincipal)->toBe(20000000)
        ->and($res2->unpaidPrincipal)->toBe(80000000)
        ->and($res2->isFullySettled)->toBeFalse();

    // Skenario 3: Pembayaran terlambat dengan ta'zir keterlambatan
    // Pokok 100jt sen, terlambat 10 hari, tarif 50 bps (0.5% per hari) -> ta'zir = 100jt * 10 * 50 / 10000 = 5jt sen (Rp 50.000)
    $res3 = RepaymentWaterfall::allocate(
        paymentAmount: 140000000, // Rp 1.400.000
        dueMargin: 30000000,      // Rp 300.000
        duePrincipal: 100000000,  // Rp 1.000.000
        tazirDailyRateBps: 50,
        daysOverdue: 10
    );

    expect($res3->allocatedMargin)->toBe(30000000)
        ->and($res3->allocatedPrincipal)->toBe(100000000)
        ->and($res3->allocatedTazir)->toBe(5000000)
        ->and($res3->excessDeposit)->toBe(5000000) // Sisa lebih Rp 50.000
        ->and($res3->isFullySettled)->toBeTrue();
});

test('sertifikat rusak pada repayment waterfall memicu exception', function () {
    $res = RepaymentWaterfall::allocate(
        paymentAmount: 100000000,
        dueMargin: 20000000,
        duePrincipal: 80000000
    );

    $corruptedCertificate = $res->certificate;
    $corruptedCertificate['hash'] = 'corrupted_tampered_hash_value';

    expect(fn () => RepaymentWaterfallCertificateVerifier::verify($corruptedCertificate))
        ->toThrow(RuntimeException::class);
});
