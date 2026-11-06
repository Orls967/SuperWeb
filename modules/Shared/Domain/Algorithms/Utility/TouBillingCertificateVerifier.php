<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

/**
 * Certificate Verifier for E1 Utility\TouBilling.
 *
 * Enforces (PR2):
 *  1. Σ energi per jendela = Σ pembacaan input (konservasi energi).
 *  2. Setiap pembacaan diklasifikasikan tepat ke satu jendela TOU.
 *  3. Perhitungan tagihan energi dan demand charge presisi integer non-negatif.
 */
final class TouBillingCertificateVerifier
{
    public function verify(TouBillingResult $result): true
    {
        $sumWindowKwh = 0;
        $calculatedEnergyChargeIdr = 0;
        $totalReadingCount = 0;

        foreach ($result->windowCharges as $wc) {
            if ($wc['kwh'] < 0) {
                throw new InvalidCertificateException("Konsumsi jendela {$wc['window']} tidak boleh negatif: {$wc['kwh']}");
            }
            if ($wc['rate_idr_per_kwh'] < 0) {
                throw new InvalidCertificateException("Tarif jendela {$wc['window']} tidak boleh negatif");
            }
            $expectedCharge = $wc['kwh'] * $wc['rate_idr_per_kwh'];
            if ($wc['charge_idr'] !== $expectedCharge) {
                throw new InvalidCertificateException("Tagihan jendela {$wc['window']} ({$wc['charge_idr']}) tidak sesuai dengan kWh x tarif ({$expectedCharge})");
            }
            $sumWindowKwh += $wc['kwh'];
            $calculatedEnergyChargeIdr += $wc['charge_idr'];
            $totalReadingCount += $wc['reading_count'];
        }

        if ($sumWindowKwh !== $result->certificate['sum_window_kwh']) {
            throw new InvalidCertificateException('Total energi jendela tidak konsisten dengan sertifikat');
        }

        if ($sumWindowKwh !== $result->certificate['total_reading_kwh']) {
            throw new InvalidCertificateException("Σ energi per jendela ({$sumWindowKwh}) tidak sama dengan Σ energi pembacaan ({$result->certificate['total_reading_kwh']})");
        }

        if ($totalReadingCount !== $result->certificate['reading_count']) {
            throw new InvalidCertificateException('Jumlah hitungan pembacaan jendela tidak cocok dengan total pembacaan');
        }

        if ($calculatedEnergyChargeIdr !== $result->energyChargeIdr) {
            throw new InvalidCertificateException("Total tagihan energi ({$result->energyChargeIdr}) tidak cocok dengan jumlah per jendela ({$calculatedEnergyChargeIdr})");
        }

        $expectedDemandCharge = $result->peakKw * $result->demandTariffIdrPerKw;
        if ($expectedDemandCharge !== $result->demandChargeIdr) {
            throw new InvalidCertificateException("Tagihan demand charge ({$result->demandChargeIdr}) tidak sesuai dengan peak kW x tarif demand ({$expectedDemandCharge})");
        }

        $expectedTotal = $result->energyChargeIdr + $result->demandChargeIdr;
        if ($result->totalChargeIdr !== $expectedTotal) {
            throw new InvalidCertificateException("Total tagihan ({$result->totalChargeIdr}) tidak cocok dengan energi + demand ({$expectedTotal})");
        }

        return true;
    }
}
