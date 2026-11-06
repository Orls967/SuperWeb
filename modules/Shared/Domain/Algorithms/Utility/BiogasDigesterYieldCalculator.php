<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use InvalidArgumentException;

/**
 * Menghitung potensi degradasi volatile solids (VS) pada digester anaerobik,
 * yield biogas (m3), potensi pembangkitan listrik WTE (kWh), energi termal (MJ),
 * dan penghindaran emisi gas rumah kaca (kg CO2e) berbasis metodologi IPCC Tier 2 / UNFCCC ACM0022.
 * Komputasi deterministik berakar integer basis points dengan sertifikat verifikasi SHA-256 (Zero-Float).
 */
final class BiogasDigesterYieldCalculator
{
    /**
     * @param  int  $wasteTonnageKg  Berat limbah basah masuk (kg)
     * @param  int  $totalSolidsBps  Total solids fraction dalam basis points (misal 2000 = 20% TS)
     * @param  int  $volatileSolidsBps  Volatile solids fraction of TS dalam basis points (misal 8000 = 80% VS)
     * @param  int  $biodegradabilityBps  Fraksi degradasi anaerobik dalam basis points (misal 7000 = 70% degradasi)
     * @param  int  $biogasYieldLitersPerKgVs  Yield biogas teoritis liter per kg VS hancur (misal 800 L/kg VS)
     * @param  int  $methaneContentBps  Kandungan gas metana (CH4) dalam basis points (misal 6000 = 60% CH4)
     * @param  int  $electricalEfficiencyBps  Efisiensi generator gas engine dalam basis points (misal 3800 = 38%)
     */
    public function compute(
        int $wasteTonnageKg,
        int $totalSolidsBps,
        int $volatileSolidsBps,
        int $biodegradabilityBps,
        int $biogasYieldLitersPerKgVs,
        int $methaneContentBps,
        int $electricalEfficiencyBps,
    ): BiogasDigesterResult {
        if ($wasteTonnageKg <= 0) {
            throw new InvalidArgumentException('Tonase limbah harus > 0 kg');
        }
        if ($totalSolidsBps <= 0 || $totalSolidsBps > 10000) {
            throw new InvalidArgumentException('Total Solids basis points harus antara 1 dan 10000');
        }
        if ($volatileSolidsBps <= 0 || $volatileSolidsBps > 10000) {
            throw new InvalidArgumentException('Volatile Solids basis points harus antara 1 dan 10000');
        }

        // 1. Massa Volatile Solids terdegradasi (kg VS):
        // Total Solids (kg) = wasteTonnageKg * totalSolidsBps / 10000
        $totalSolidsKg = (int) intdiv($wasteTonnageKg * $totalSolidsBps, 10000);

        // Volatile Solids (kg) = totalSolidsKg * volatileSolidsBps / 10000
        $volatileSolidsKg = (int) intdiv($totalSolidsKg * $volatileSolidsBps, 10000);

        // Degraded VS (kg) = volatileSolidsKg * biodegradabilityBps / 10000
        $degradedVsKg = (int) intdiv($volatileSolidsKg * $biodegradabilityBps, 10000);

        // 2. Volume Biogas yang dihasilkan (m3):
        // Total liter = degradedVsKg * biogasYieldLitersPerKgVs -> m3 = liter / 1000
        $biogasYieldM3 = (int) intdiv($degradedVsKg * $biogasYieldLitersPerKgVs, 1000);

        // 3. Volume Metana murni (CH4 m3) = biogasYieldM3 * methaneContentBps / 10000
        $methaneM3 = (int) intdiv($biogasYieldM3 * $methaneContentBps, 10000);

        // 4. Kandungan Energi Kalori:
        // Nilai kalor rendah metana = 35.8 MJ/m3 (dinyatakan sebagai 35,800 kJ/m3)
        // Total energi termal dalam MJ:
        $thermalEnergyMj = (int) intdiv($methaneM3 * 35800, 1000);

        // 5. Potensi Listrik Bruto (kWh):
        // 1 kWh = 3.6 MJ -> energi listrik primer = thermalEnergyMj / 3.6 * electricalEfficiency
        // Komputasi integer: (thermalEnergyMj * 10 * electricalEfficiencyBps) / (36 * 10000)
        $electricGrossKwh = (int) intdiv($thermalEnergyMj * $electricalEfficiencyBps, 36000);

        // 6. Penghindaran Emisi Metana Landfill (kg CO2e):
        // Densitas CH4 = 0.717 kg/m3; GWP20 Metana = 28 kg CO2e / kg CH4
        // Emisi CH4 = methaneM3 * 0.717 * 28 kg CO2e
        // Integer bps: (methaneM3 * 717 * 28) / 1000 = methaneM3 * 20.076
        $avoidedEmissionCo2eKg = (int) intdiv($methaneM3 * 20076, 1000);

        // 7. Sertifikat SHA-256
        $rawSignature = sprintf(
            'BIOGAS-WTE|KG:%d|TS:%d|VS:%d|DEG:%d|BG_M3:%d|CH4_M3:%d|MJ:%d|KWH:%d|CO2E:%d',
            $wasteTonnageKg,
            $totalSolidsBps,
            $volatileSolidsBps,
            $degradedVsKg,
            $biogasYieldM3,
            $methaneM3,
            $thermalEnergyMj,
            $electricGrossKwh,
            $avoidedEmissionCo2eKg
        );
        $certificateHash = hash('sha256', $rawSignature);

        return new BiogasDigesterResult(
            wasteTonnageKg: $wasteTonnageKg,
            totalSolidsKg: $totalSolidsKg,
            volatileSolidsKg: $volatileSolidsKg,
            degradedVsKg: $degradedVsKg,
            biogasYieldM3: $biogasYieldM3,
            methaneM3: $methaneM3,
            thermalEnergyMj: $thermalEnergyMj,
            electricGrossKwh: $electricGrossKwh,
            avoidedEmissionCo2eKg: $avoidedEmissionCo2eKg,
            certificateHash: $certificateHash,
            canonicalPayload: $rawSignature,
        );
    }
}
