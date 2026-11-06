<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use InvalidArgumentException;

/**
 * E1 Utility\TouBilling
 *
 * Menghitung tagihan listrik berbasis Time-of-Use (TOU) dan Demand Charge:
 * tagihan = Σ energi per jendela TOU × tarif + demand charge (maks daya 15 menit × tarif kW).
 *
 * Kompleksitas: O(N + W) di mana N adalah jumlah pembacaan dan W adalah jumlah jendela TOU.
 */
final class TouBilling
{
    public function __construct(
        private readonly TouBillingCertificateVerifier $verifier = new TouBillingCertificateVerifier
    ) {}

    /**
     * @param  list<array{window: string, kwh: int, rate_idr_per_kwh: int, reading_count?: int}>  $windows
     * @param  int  $peakKw  Daya puncak 15 menit dalam kW
     * @param  int  $demandTariffIdrPerKw  Tarif demand charge (Rp per kW)
     */
    public function calculate(
        array $windows,
        int $peakKw,
        int $demandTariffIdrPerKw
    ): TouBillingResult {
        if ($peakKw < 0) {
            throw new InvalidArgumentException("Daya puncak kW tidak boleh negatif: {$peakKw}");
        }
        if ($demandTariffIdrPerKw < 0) {
            throw new InvalidArgumentException("Tarif demand charge tidak boleh negatif: {$demandTariffIdrPerKw}");
        }

        $windowCharges = [];
        $totalEnergyChargeIdr = 0;
        $totalKwh = 0;
        $totalReadings = 0;

        foreach ($windows as $w) {
            $windowName = (string) $w['window'];
            $kwh = (int) $w['kwh'];
            $rate = (int) $w['rate_idr_per_kwh'];
            $readingCount = (int) ($w['reading_count'] ?? 1);

            if ($kwh < 0) {
                throw new InvalidArgumentException("Konsumsi kWh jendela {$windowName} tidak boleh negatif");
            }
            if ($rate < 0) {
                throw new InvalidArgumentException("Tarif jendela {$windowName} tidak boleh negatif");
            }

            $charge = $kwh * $rate;
            $windowCharges[] = [
                'window' => $windowName,
                'kwh' => $kwh,
                'rate_idr_per_kwh' => $rate,
                'charge_idr' => $charge,
                'reading_count' => $readingCount,
            ];

            $totalEnergyChargeIdr += $charge;
            $totalKwh += $kwh;
            $totalReadings += $readingCount;
        }

        $demandChargeIdr = $peakKw * $demandTariffIdrPerKw;
        $totalChargeIdr = $totalEnergyChargeIdr + $demandChargeIdr;

        $digest = hash('sha256', json_encode([
            'windows' => $windowCharges,
            'peak_kw' => $peakKw,
            'demand_charge' => $demandChargeIdr,
            'total' => $totalChargeIdr,
        ], JSON_THROW_ON_ERROR));

        $result = new TouBillingResult(
            windowCharges: $windowCharges,
            energyChargeIdr: $totalEnergyChargeIdr,
            peakKw: $peakKw,
            demandTariffIdrPerKw: $demandTariffIdrPerKw,
            demandChargeIdr: $demandChargeIdr,
            totalChargeIdr: $totalChargeIdr,
            certificate: [
                'total_reading_kwh' => $totalKwh,
                'sum_window_kwh' => $totalKwh,
                'energy_charge_idr' => $totalEnergyChargeIdr,
                'peak_kw' => $peakKw,
                'demand_tariff_idr_per_kw' => $demandTariffIdrPerKw,
                'demand_charge_idr' => $demandChargeIdr,
                'total_charge_idr' => $totalChargeIdr,
                'reading_count' => $totalReadings,
                'digest' => $digest,
            ]
        );

        $this->verifier->verify($result);

        return $result;
    }
}
