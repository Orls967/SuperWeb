<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Services;

use DomainException;

/**
 * Kalkulator Geometri Lambung & Stabilitas Kapal Utuh (Intact Stability).
 * Standar: IMO International Code on Intact Stability, 2008 (2008 IS Code).
 */
final class MaritimeVesselGeometryCalculator
{
    /**
     * Hitung koefisien bentuk hidrodinamis lambung kapal niaga.
     *
     * @param float $displacementVolumeM3 Volume benaman kapal (m3)
     * @param float $lengthBetweenPerpendicularsM Panjang antar garis tegak (LBP dalam meter)
     * @param float $beamM Lebar kapal (meter)
     * @param float $draftM Sarat air kapal (meter)
     * @param float $midshipAreaM2 Luas penampang gading besar (midship section area m2)
     * @param float $waterplaneAreaM2 Luas bidang garis air (waterplane area m2)
     * @return array{
     *     block_coefficient_cb: float,
     *     midship_coefficient_cm: float,
     *     prismatic_coefficient_cp: float,
     *     waterplane_coefficient_cwp: float
     * }
     */
    public function calculateHullFormCoefficients(
        float $displacementVolumeM3,
        float $lengthBetweenPerpendicularsM,
        float $beamM,
        float $draftM,
        float $midshipAreaM2,
        float $waterplaneAreaM2
    ): array {
        $circumscribedBoxM3 = $lengthBetweenPerpendicularsM * $beamM * $draftM;
        if ($circumscribedBoxM3 <= 0.0) {
            throw new DomainException('Dimensi utama kapal harus lebih besar dari 0.');
        }

        // Koefisien Blok (Block Coefficient): Cb = V / (L * B * T)
        $cb = $displacementVolumeM3 / $circumscribedBoxM3;

        // Koefisien Midship: Cm = Am / (B * T)
        $cm = $midshipAreaM2 / ($beamM * $draftM);

        // Koefisien Prismatik: Cp = Cb / Cm
        $cp = $cm > 0 ? ($cb / $cm) : $cb;

        // Koefisien Bidang Air: Cwp = Awp / (L * B)
        $cwp = $waterplaneAreaM2 / ($lengthBetweenPerpendicularsM * $beamM);

        return [
            'block_coefficient_cb' => round($cb, 4),
            'midship_coefficient_cm' => round($cm, 4),
            'prismatic_coefficient_cp' => round($cp, 4),
            'waterplane_coefficient_cwp' => round($cwp, 4),
        ];
    }

    /**
     * Hitung tinggi metasentris awal (Initial Metacentric Height / GM) dan kriteria stabilitas IMO.
     * Kriteria IMO IS Code Bagian 2.2:
     * - GM awal tidak boleh kurang dari 0.15 meter untuk kapal barang umum.
     * - Untuk kapal muatan curah butiran (grain bulk), GM tidak boleh kurang dari 0.30 meter.
     *
     * @param float $keelToCenterOfBuoyancyM Jarak lunas ke titik apung (KB)
     * @param float $metacentricRadiusM Jari-jari metasentris (BM = IT / V)
     * @param float $keelToCenterOfGravityM Jarak lunas ke titik berat kapal (KG)
     * @param float $freeSurfaceCorrectionM Koreksi efek permukaan bebas cairan dalam tangki (FSC)
     * @param bool $isGrainCarrier Kapal bermuatan curah butiran
     * @return array{
     *     solid_gm_m: float,
     *     fluid_gm_m: float,
     *     is_imo_compliant: bool,
     *     minimum_required_gm_m: float,
     *     status: string
     * }
     */
    public function evaluateIntactStabilityGm(
        float $keelToCenterOfBuoyancyM,
        float $metacentricRadiusM,
        float $keelToCenterOfGravityM,
        float $freeSurfaceCorrectionM = 0.05,
        bool $isGrainCarrier = false
    ): array {
        // KM = KB + BM
        $km = $keelToCenterOfBuoyancyM + $metacentricRadiusM;

        // Solid GM = KM - KG
        $solidGm = $km - $keelToCenterOfGravityM;

        // Fluid GM (koreksi tangki berombak) = Solid GM - FSC
        $fluidGm = $solidGm - $freeSurfaceCorrectionM;

        $minRequired = $isGrainCarrier ? 0.30 : 0.15;
        $isCompliant = ($fluidGm >= $minRequired);

        return [
            'solid_gm_m' => round($solidGm, 3),
            'fluid_gm_m' => round($fluidGm, 3),
            'is_imo_compliant' => $isCompliant,
            'minimum_required_gm_m' => $minRequired,
            'status' => $isCompliant ? 'STABLE_IMO_COMPLIANT' : 'CRITICAL_TENDER_NEGATIVE_STABILITY',
        ];
    }
}
