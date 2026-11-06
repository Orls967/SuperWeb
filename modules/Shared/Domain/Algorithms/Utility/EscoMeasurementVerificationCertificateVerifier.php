<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use RuntimeException;

/**
 * Verifier sertifikat invarian kalkulasi ESCO M&V.
 */
final class EscoMeasurementVerificationCertificateVerifier
{
    public function verify(EscoMeasurementVerificationResult $result): bool
    {
        if ($result->baselineKwh < 0 || $result->actualKwh < 0 || $result->rateIdrPerKwh < 0) {
            throw new RuntimeException('Nilai baseline, aktual, atau tarif tidak boleh negatif');
        }

        $expectedAdjusted = (int) intdiv($result->baselineKwh * $result->cddFactorBps, 10000);
        if ($expectedAdjusted !== $result->adjustedBaselineKwh) {
            throw new RuntimeException("Adjusted baseline ({$result->adjustedBaselineKwh}) tidak sesuai dengan formula ({$expectedAdjusted})");
        }

        $expectedGross = $result->adjustedBaselineKwh - $result->actualKwh;
        if ($expectedGross !== $result->grossSavingsKwh) {
            throw new RuntimeException("Gross savings ({$result->grossSavingsKwh}) tidak konsisten dengan selisih baseline dan aktual ({$expectedGross})");
        }

        $expectedTotalSavingsIdr = $result->grossSavingsKwh * $result->rateIdrPerKwh;
        if ($expectedTotalSavingsIdr !== $result->totalSavingsIdr) {
            throw new RuntimeException("Total savings Rp ({$result->totalSavingsIdr}) tidak konsisten dengan perkalian tarif ({$expectedTotalSavingsIdr})");
        }

        $expectedSum = $result->escoSavingsShareIdr + $result->clientSavingsShareIdr;
        if ($expectedSum !== $result->totalSavingsIdr) {
            throw new RuntimeException("Penjumlahan bagi hasil ESCO ({$result->escoSavingsShareIdr}) + klien ({$result->clientSavingsShareIdr}) tidak sama dengan total ({$result->totalSavingsIdr})");
        }

        if ($result->thermalKwhTh !== null && $result->plantElectricKwh !== null) {
            if ($result->plantElectricKwh <= 0) {
                throw new RuntimeException('Konsumsi listrik chiller plant harus positif');
            }
            $expectedCopBps = (int) intdiv($result->thermalKwhTh * 100, $result->plantElectricKwh);
            if ($expectedCopBps !== $result->copBps) {
                throw new RuntimeException("COP bps ({$result->copBps}) tidak konsisten ({$expectedCopBps})");
            }
        }

        return true;
    }
}
