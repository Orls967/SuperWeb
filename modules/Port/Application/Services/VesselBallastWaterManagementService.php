<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use Modules\Port\Domain\Models\FleetVessel;

/**
 * Service Pengelolaan Air Ballas Kapal Niaga & Kepatuhan IMO BWMC.
 * Standar: International Convention for the Control and Management of Ships' Ballast Water and Sediments (BWM Convention).
 */
final class VesselBallastWaterManagementService
{
    /**
     * Audit pertukaran air ballas di laut lepas (Ballast Water Exchange / Standard D-1).
     * Aturan Regulation B-4:
     * - Pertukaran dilakukan minimal 200 mil laut dari daratan terdekat dan kedalaman air minimal 200 meter.
     * - Jika tidak memungkinkan, minimal 50 mil laut dan kedalaman minimal 200 meter.
     * - Efisiensi pertukaran volume minimal 95% (metode sequential atau flow-through 3 kali volume tangki).
     *
     * @param  float  $distanceFromCoastNm  Jarak dari garis pantai terdekat (mil laut)
     * @param  float  $waterDepthM  Kedalaman perairan laut (meter)
     * @param  float  $tankCapacityM3  Kapasitas tangki ballas
     * @param  float  $volumePumpedM3  Volume air yang dipompa untuk pertukaran
     * @param  string  $method  SEQUENTIAL atau FLOW_THROUGH
     * @return array{
     *     is_d1_compliant: bool,
     *     distance_from_coast_nm: float,
     *     water_depth_m: float,
     *     volumetric_exchange_efficiency_pct: float,
     *     compliance_status: string,
     *     record_book_entry: string
     * }
     */
    public function auditBallastWaterExchangeD1(
        float $distanceFromCoastNm,
        float $waterDepthM,
        float $tankCapacityM3,
        float $volumePumpedM3,
        string $method = 'SEQUENTIAL'
    ): array {
        $distanceValid = ($distanceFromCoastNm >= 50.0);
        $depthValid = ($waterDepthM >= 200.0);

        // Metode flow-through mensyaratkan 3x volume tangki untuk mencapai 95% efisiensi pertukaran
        $requiredVolume = $method === 'FLOW_THROUGH' ? ($tankCapacityM3 * 3.0) : ($tankCapacityM3 * 0.95);
        $efficiencyPct = min(100.0, ($volumePumpedM3 / max(1.0, $tankCapacityM3)) * 100.0);
        $volumeValid = ($volumePumpedM3 >= $requiredVolume);

        $isCompliant = $distanceValid && $depthValid && $volumeValid;

        $status = $isCompliant
            ? 'COMPLIANT_D1'
            : 'NON_COMPLIANT_DISCHARGE_PROHIBITED';

        $entry = "Pertukaran air ballas {$method}: {$volumePumpedM3} m3 pada jarak {$distanceFromCoastNm} NM dan kedalaman {$waterDepthM} m.";

        return [
            'is_d1_compliant' => $isCompliant,
            'distance_from_coast_nm' => round($distanceFromCoastNm, 1),
            'water_depth_m' => round($waterDepthM, 1),
            'volumetric_exchange_efficiency_pct' => round($efficiencyPct, 1),
            'compliance_status' => $status,
            'record_book_entry' => $entry,
        ];
    }

    /**
     * Audit kinerja sistem pengolahan air ballas di kapal (Ballast Water Treatment System / Standard D-2).
     * Standar D-2: < 10 organisme viabel per m3 untuk ukuran >= 50 mikrometer.
     */
    public function verifyBwtsOperationD2(
        int $vesselId,
        string $treatmentTechnology, // ELECTRO_CHLORINATION, UV_IRRADIATION
        float $totalResidualOxidantPpm,
        float $uvTransmittancePct,
        float $flowRateM3H
    ): array {
        $vessel = FleetVessel::findOrFail($vesselId);
        $violations = [];

        if ($treatmentTechnology === 'ELECTRO_CHLORINATION') {
            // TRO harus berada di rentang 1.5 - 2.5 ppm saat treatment, dan < 0.1 ppm saat netralisasi de-ballasting
            if ($totalResidualOxidantPpm < 1.0) {
                $violations[] = 'Konsentrasi Total Residual Oxidant (TRO) terlalu rendah untuk disinfeksi efektif.';
            }
        } elseif ($treatmentTechnology === 'UV_IRRADIATION') {
            // UV-T air keruh tidak boleh < 50%
            if ($uvTransmittancePct < 50.0) {
                $violations[] = 'Transmitansi UV air keruh terlalu rendah (< 50%). Dosis radiasi tidak mencukupi standar D-2.';
            }
        }

        $isD2Compliant = empty($violations);

        return [
            'vessel_id' => $vessel->id,
            'technology' => $treatmentTechnology,
            'is_d2_compliant' => $isD2Compliant,
            'flow_rate_m3_h' => $flowRateM3H,
            'violations' => $violations,
            'approval_code' => $isD2Compliant ? 'IMO_D2_TYPE_APPROVED' : 'TREATMENT_ALARM_DISCHARGE_ABORTED',
        ];
    }
}
