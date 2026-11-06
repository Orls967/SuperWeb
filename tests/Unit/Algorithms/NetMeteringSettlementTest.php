<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Energy\NetMeteringSettlement;
use Modules\Shared\Domain\Algorithms\Energy\NetMeteringSettlementCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Energy\NetMeteringSettlementResult;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

it('matches prompt vector for E3 Energy\NetMeteringSettlement', function (): void {
    $settler = new NetMeteringSettlement;

    // Vektor (kWh): produksi [0,5,12,8,0], konsumsi [4,6,7,9,3] → ekspor 5, impor 9;
    // tarif 1.444, feed-in 1.000 → 7.996.
    $productions = [0, 5, 12, 8, 0];
    $consumptions = [4, 6, 7, 9, 3];

    $result = $settler->settle(
        productions: $productions,
        consumptions: $consumptions,
        tariffIdrPerKwh: 1444,
        feedInTariffIdrPerKwh: 1000
    );

    expect($result->totalExportKwh)->toBe(5)
        ->and($result->totalImportKwh)->toBe(9)
        ->and($result->grossImportCostIdr)->toBe(12996)
        ->and($result->exportCreditIdr)->toBe(5000)
        ->and($result->netBillIdr)->toBe(7996);
});

it('throws InvalidCertificateException when E3 NetMeteringSettlement certificate is tampered', function (): void {
    $verifier = new NetMeteringSettlementCertificateVerifier;

    $corruptedResult = new NetMeteringSettlementResult(
        totalExportKwh: 5,
        totalImportKwh: 9,
        tariffIdrPerKwh: 1444,
        feedInTariffIdrPerKwh: 1000,
        grossImportCostIdr: 12996,
        exportCreditIdr: 5000,
        netBillIdr: 1000, // Tampered net bill
        intervals: [
            ['interval' => 0, 'production_kwh' => 0, 'consumption_kwh' => 4, 'export_kwh' => 0, 'import_kwh' => 4],
        ],
        certificate: [
            'total_production_kwh' => 0,
            'total_consumption_kwh' => 4,
            'total_export_kwh' => 5,
            'total_import_kwh' => 9,
            'tariff_idr_per_kwh' => 1444,
            'feed_in_tariff_idr_per_kwh' => 1000,
            'gross_import_cost_idr' => 12996,
            'export_credit_idr' => 5000,
            'net_bill_idr' => 1000,
            'digest' => 'corrupted',
        ]
    );

    expect(fn () => $verifier->verify($corruptedResult))
        ->toThrow(InvalidCertificateException::class);
});
