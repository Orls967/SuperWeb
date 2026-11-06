<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use InvalidArgumentException;

/**
 * E6 Utility\EscoMeasurementVerification
 *
 * Algoritma bersertifikat verifikasi pengukuran penghematan energi ESCO (IPMVP Option C)
 * dan analisis penghematan chiller district cooling vs AC individual.
 *
 * Kompleksitas: O(1) waktu dan ruang.
 */
final class EscoMeasurementVerification
{
    public function __construct(
        private readonly EscoMeasurementVerificationCertificateVerifier $verifier = new EscoMeasurementVerificationCertificateVerifier
    ) {}

    /**
     * Hitung penghematan energi bergaransi & bagi hasil ESCO serta performa district cooling.
     *
     * @param  int  $baselineKwh  Baseline konsumsi historis (kWh)
     * @param  int  $cddFactorBps  Faktor penyesuaian cuaca (CDD) dalam bps (10000 = 1.0, 11000 = 1.1)
     * @param  int  $actualKwh  Konsumsi riil periode berjalan (kWh)
     * @param  int  $rateIdrPerKwh  Tarif dasar listrik (Rp/kWh)
     * @param  int  $escoShareBps  Rasio bagi hasil untuk ESCO dalam bps (7000 = 70%)
     * @param  int|null  $thermalKwhTh  Total produksi dingin district cooling (kWh_th)
     * @param  int|null  $plantElectricKwh  Listrik aktual plant chiller (kWh)
     * @param  int|null  $baselineCopBps  COP pembanding AC konvensional individual dalam bps (e.g. 300 = 3.0)
     */
    public function calculate(
        int $baselineKwh,
        int $cddFactorBps,
        int $actualKwh,
        int $rateIdrPerKwh,
        int $escoShareBps,
        ?int $thermalKwhTh = null,
        ?int $plantElectricKwh = null,
        ?int $baselineCopBps = null
    ): EscoMeasurementVerificationResult {
        if ($baselineKwh < 0) {
            throw new InvalidArgumentException("Baseline kWh tidak boleh negatif: {$baselineKwh}");
        }
        if ($cddFactorBps < 0) {
            throw new InvalidArgumentException("CDD factor tidak boleh negatif: {$cddFactorBps}");
        }
        if ($actualKwh < 0) {
            throw new InvalidArgumentException("Actual kWh tidak boleh negatif: {$actualKwh}");
        }
        if ($rateIdrPerKwh < 0) {
            throw new InvalidArgumentException("Tarif tidak boleh negatif: {$rateIdrPerKwh}");
        }
        if ($escoShareBps < 0 || $escoShareBps > 10000) {
            throw new InvalidArgumentException("ESCO share bps harus antara 0 dan 10000: {$escoShareBps}");
        }

        $adjustedBaselineKwh = (int) intdiv($baselineKwh * $cddFactorBps, 10000);
        $grossSavingsKwh = $adjustedBaselineKwh - $actualKwh;
        $totalSavingsIdr = $grossSavingsKwh * $rateIdrPerKwh;

        $escoSavingsShareIdr = (int) intdiv($totalSavingsIdr * $escoShareBps, 10000);
        $clientSavingsShareIdr = $totalSavingsIdr - $escoSavingsShareIdr;

        $copBps = null;
        $districtCoolingSavingsKwh = null;

        if ($thermalKwhTh !== null && $plantElectricKwh !== null) {
            if ($plantElectricKwh <= 0) {
                throw new InvalidArgumentException('Plant electric kWh harus positif jika thermal kWh diisi');
            }
            $copBps = (int) intdiv($thermalKwhTh * 100, $plantElectricKwh);

            if ($baselineCopBps !== null && $baselineCopBps > 0) {
                $individualAcElectricKwh = (int) intdiv($thermalKwhTh * 100, $baselineCopBps);
                $districtCoolingSavingsKwh = $individualAcElectricKwh - $plantElectricKwh;
            }
        }

        $result = new EscoMeasurementVerificationResult(
            baselineKwh: $baselineKwh,
            cddFactorBps: $cddFactorBps,
            adjustedBaselineKwh: $adjustedBaselineKwh,
            actualKwh: $actualKwh,
            grossSavingsKwh: $grossSavingsKwh,
            rateIdrPerKwh: $rateIdrPerKwh,
            totalSavingsIdr: $totalSavingsIdr,
            escoShareBps: $escoShareBps,
            escoSavingsShareIdr: $escoSavingsShareIdr,
            clientSavingsShareIdr: $clientSavingsShareIdr,
            thermalKwhTh: $thermalKwhTh,
            plantElectricKwh: $plantElectricKwh,
            copBps: $copBps,
            baselineCopBps: $baselineCopBps,
            districtCoolingSavingsKwh: $districtCoolingSavingsKwh,
            certified: true
        );

        $this->verifier->verify($result);

        return $result;
    }
}
