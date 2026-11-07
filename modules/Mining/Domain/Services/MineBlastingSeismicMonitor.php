<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use InvalidArgumentException;

/**
 * Pemantau Getaran Seismik Peledakan & Airblast Tambang Terbuka (D94-5).
 *
 * Mengimplementasikan kalkulasi getaran tanah USBM RI 8507 & SNI 7571:2010:
 * 1. Jarak Berskala (Scaled Distance / SD):
 *    SD = Distance (m) / sqrt(Max Instantaneous Charge (kg))
 * 2. Kecepatan Partikel Puncak (Peak Particle Velocity / PPV):
 *    PPV = K * (SD)^(-b) mm/s
 *    Konstanta situs tipikal batuan keras: K = 1140, b = 1.6
 * 3. Tekanan Gelombang Udara (Airblast Overpressure):
 *    L = 165 - 24 * log10(Distance / cbrt(Charge)) dBL
 * 4. Verifikasi Batas Aman Bangunan & Struktur (SNI 7571:2010 Kelas II Pemukiman: max 3.0 - 5.0 mm/s).
 */
final class MineBlastingSeismicMonitor
{
    /**
     * @param array{
     *     blast_event_id: string,
     *     distance_meters: float,
     *     charge_per_delay_kg: float,
     *     dominant_frequency_hz?: float,
     *     structure_class?: string,
     *     site_constant_k?: float,
     *     site_exponent_b?: float
     * } $data
     * @return array{
     *     blast_event_id: string,
     *     scaled_distance: float,
     *     predicted_ppv_mms: float,
     *     predicted_airblast_dbl: float,
     *     regulatory_ppv_limit_mms: float,
     *     regulatory_airblast_limit_dbl: float,
     *     is_seismic_compliant: bool,
     *     is_airblast_compliant: bool,
     *     overall_safety_status: string,
     *     mitigation_directive: string
     * }
     */
    public function evaluate(array $data): array
    {
        $blastId = trim((string) $data['blast_event_id']);
        $distance = (float) $data['distance_meters'];
        $chargeKg = (float) $data['charge_per_delay_kg'];
        $freqHz = (float) ($data['dominant_frequency_hz'] ?? 30.0);
        $structClass = (string) ($data['structure_class'] ?? 'RESIDENTIAL');
        $k = (float) ($data['site_constant_k'] ?? 1140.0);
        $b = (float) ($data['site_exponent_b'] ?? 1.6);

        if ($distance <= 0.0 || $chargeKg <= 0.0 || $k <= 0.0 || $b <= 0.0) {
            throw new InvalidArgumentException('Jarak peledakan, muatan bahan peledak, dan konstanta situs harus bernilai positif.');
        }

        // 1. Scaled Distance (SD)
        $scaledDistance = round($distance / sqrt($chargeKg), 3);

        // 2. Predicted PPV (mm/s)
        $ppv = round($k * pow($scaledDistance, -$b), 2);

        // 3. Predicted Airblast Overpressure (dBL)
        $cubeRootCharge = pow($chargeKg, 1.0 / 3.0);
        $cubeScaledDistance = max(1.0, $distance / $cubeRootCharge);
        $airblastDbl = round(165.0 - (24.0 * log10($cubeScaledDistance)), 1);

        // 4. Batas SNI 7571:2010
        $ppvLimit = match ($structClass) {
            'HERITAGE' => 2.0,
            'RESIDENTIAL' => $freqHz < 40.0 ? 3.0 : 5.0,
            'INDUSTRIAL' => 12.0,
            default => 5.0,
        };

        $airblastLimit = 120.0; // Kepmen LH / SNI batas kebisingan impulsif

        $seismicOk = $ppv <= $ppvLimit;
        $airblastOk = $airblastDbl <= $airblastLimit;

        $overallStatus = ($seismicOk && $airblastOk) ? 'AMAN_TERKENDALI' : 'POTENSI_KERUSAKAN_STRUKTURAL';

        $directive = match (true) {
            ! $seismicOk && ! $airblastOk => "BAHAYA KRITIS: PPV ({$ppv} mm/s > {$ppvLimit} mm/s) dan Airblast ({$airblastDbl} dBL > {$airblastLimit} dBL) melebihi ambang batas. Turunkan muatan per delay minimal 30% dan pasang stemming matting.",
            ! $seismicOk => "PERINGATAN SEISMIK: PPV ({$ppv} mm/s > {$ppvLimit} mm/s) melampaui batas aman struktur {$structClass}. Desain ulang firing sequence dengan interval delay elektronik minimal 25 ms.",
            ! $airblastOk => "PERINGATAN AIRBLAST: Suara impulsif ({$airblastDbl} dBL > {$airblastLimit} dBL) mengancam kenyamanan area hunian. Periksa kualitas stemming kolom lubang ledak.",
            default => 'Desain peledakan valid dan mematuhi kaidah baku tingkat getaran peledakan SNI 7571:2010.',
        };

        return [
            'blast_event_id' => $blastId,
            'scaled_distance' => $scaledDistance,
            'predicted_ppv_mms' => $ppv,
            'predicted_airblast_dbl' => $airblastDbl,
            'regulatory_ppv_limit_mms' => $ppvLimit,
            'regulatory_airblast_limit_dbl' => $airblastLimit,
            'is_seismic_compliant' => $seismicOk,
            'is_airblast_compliant' => $airblastOk,
            'overall_safety_status' => $overallStatus,
            'mitigation_directive' => $directive,
        ];
    }
}
