<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Islamic\IjarahResult;
use Modules\Shared\Domain\Algorithms\Islamic\IjarahSchedule;
use Modules\Shared\Domain\Algorithms\Islamic\IjarahScheduleCertificateVerifier;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

test('vektor algoritma ijarah schedule dan amortisasi imbt', function (): void {
    $algo = new IjarahSchedule();

    // Vektor prompt: biaya aset 120.000.000; 12 bulan; total ujrah 132.000.000
    // -> ujrah 11.000.000/bulan; penyusutan 10.000.000/bulan; harga opsi IMBT 1.000.000
    // -> setelah eksekusi aset keluar buku, nilai buku 0.
    $result = $algo->generate(
        assetCostIdr: 120_000_000,
        tenorMonths: 12,
        totalUjrahIdr: 132_000_000,
        residualOptionPriceIdr: 0 // biaya aset disusutkan penuh hingga nilai opsi residual
    );

    expect($result->sumUjrahIdr)->toBe(132_000_000)
        ->and($result->sumDepreciationIdr)->toBe(120_000_000)
        ->and($result->periods[0]['ujrah_idr'])->toBe(11_000_000)
        ->and($result->periods[0]['depreciation_idr'])->toBe(10_000_000)
        ->and($result->periods[11]['carrying_book_value_idr'])->toBe(0);

    // Eksekusi opsi IMBT dengan harga 1.000.000
    $imbtResult = (new IjarahSchedule())->generate(
        assetCostIdr: 120_000_000,
        tenorMonths: 12,
        totalUjrahIdr: 132_000_000,
        residualOptionPriceIdr: 1_000_000
    );
    expect($imbtResult->endingBookValueIdr)->toBe(1_000_000);

    $exercise = $imbtResult->exerciseImbtOption();
    expect($exercise['transferred'])->toBeTrue()
        ->and($exercise['final_carrying_book_value_idr'])->toBe(0);
});

test('sertifikat rusak pada ijarah schedule memicu exception', function (): void {
    $corrupt = new IjarahResult(
        assetCostIdr: 120_000_000,
        tenorMonths: 12,
        totalUjrahIdr: 132_000_000,
        residualOptionPriceIdr: 0,
        periods: [
            [
                'period' => 1,
                'ujrah_idr' => 10_000_000, // Rusak: seharusnya 11m
                'depreciation_idr' => 10_000_000,
                'accumulated_depreciation_idr' => 10_000_000,
                'carrying_book_value_idr' => 110_000_000,
            ],
        ],
        sumUjrahIdr: 10_000_000,
        sumDepreciationIdr: 10_000_000,
        endingBookValueIdr: 110_000_000
    );

    (new IjarahScheduleCertificateVerifier())->verify($corrupt);
})->throws(InvalidCertificateException::class);
