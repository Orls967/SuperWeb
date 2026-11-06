<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Utility\TouBilling;
use Modules\Shared\Domain\Algorithms\Utility\TouBillingCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Utility\TouBillingResult;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

it('matches prompt vector for E1 Utility\TouBilling and verifies certificate', function (): void {
    $calculator = new TouBilling;

    // Vektor: off-peak 120 kWh @ 1.000, bahu 200 kWh @ 1.400, puncak 80 kWh @ 2.100 → 568.000;
    // maks 30 kW × 35.000 → 1.050.000; total 1.618.000.
    $windows = [
        ['window' => 'off_peak', 'kwh' => 120, 'rate_idr_per_kwh' => 1000],
        ['window' => 'shoulder', 'kwh' => 200, 'rate_idr_per_kwh' => 1400],
        ['window' => 'peak', 'kwh' => 80, 'rate_idr_per_kwh' => 2100],
    ];

    $result = $calculator->calculate(
        windows: $windows,
        peakKw: 30,
        demandTariffIdrPerKw: 35000
    );

    expect($result->energyChargeIdr)->toBe(568000)
        ->and($result->demandChargeIdr)->toBe(1050000)
        ->and($result->totalChargeIdr)->toBe(1618000)
        ->and($result->certificate['sum_window_kwh'])->toBe(400);
});

it('throws InvalidCertificateException when E1 TouBilling certificate is corrupted', function (): void {
    $verifier = new TouBillingCertificateVerifier;

    $corruptedResult = new TouBillingResult(
        windowCharges: [
            ['window' => 'off_peak', 'kwh' => 120, 'rate_idr_per_kwh' => 1000, 'charge_idr' => 120000, 'reading_count' => 1],
        ],
        energyChargeIdr: 120000,
        peakKw: 30,
        demandTariffIdrPerKw: 35000,
        demandChargeIdr: 999999, // Tampered demand charge
        totalChargeIdr: 1119999,
        certificate: [
            'total_reading_kwh' => 120,
            'sum_window_kwh' => 120,
            'energy_charge_idr' => 120000,
            'peak_kw' => 30,
            'demand_tariff_idr_per_kw' => 35000,
            'demand_charge_idr' => 999999,
            'total_charge_idr' => 1119999,
            'reading_count' => 1,
            'digest' => 'corrupted',
        ]
    );

    expect(fn () => $verifier->verify($corruptedResult))
        ->toThrow(InvalidCertificateException::class);
});
