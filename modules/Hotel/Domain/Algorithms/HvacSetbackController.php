<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

/**
 * H4 Hotel\HvacSetbackController [Fase 91]
 *
 * Kamar terjual + tanpa gerak >= 15 menit -> setback (+3 C); gerak -> kembali normal.
 * Kamar tidak terjual -> mode hemat penuh.
 * Hemat energi = menit setback * daya AC (W) * faktor hemat (bps) / (60 * 10.000).
 * Satuan hasil: Wh (Watt-hour).
 */
final class HvacSetbackController
{
    /**
     * @param bool $isOccupiedRoom Apakah kamar terjual/terhuni tamu
     * @param int $minutesSinceLastMotion Durasi menit sejak deteksi gerak terakhir
     * @param int $durationMinutes Total durasi periode observasi (menit)
     * @param int $acPowerWatt Daya konsumsi pendingin ruangan (Watt, mis. 1.200 W)
     * @param int $savingFactorBps Faktor efisiensi setback dalam basis points (mis. 3.000 bps = 30%)
     * @return array{
     *     setback_active: bool,
     *     setback_temp_delta_c: float,
     *     setback_minutes: int,
     *     energy_saved_wh: int,
     *     certificate: HvacSetbackCertificate
     * }
     */
    public static function evaluate(
        bool $isOccupiedRoom,
        int $minutesSinceLastMotion,
        int $durationMinutes,
        int $acPowerWatt = 1200,
        int $savingFactorBps = 3000
    ): array {
        if ($durationMinutes < 0 || $minutesSinceLastMotion < 0 || $acPowerWatt < 0) {
            throw new InvalidArgumentException("Parameter durasi dan daya tidak boleh negatif.");
        }

        if (!$isOccupiedRoom) {
            // Kamar tidak terjual -> mode hemat penuh sepanjang durasi
            $setbackMinutes = $durationMinutes;
            $deltaC = 5.0; // Deep setback
            $setbackActive = true;
        } else {
            // Kamar terjual -> aktif jika tanpa gerak >= 15 menit
            if ($minutesSinceLastMotion >= 15) {
                $setbackMinutes = max(0, $durationMinutes - 15);
                $deltaC = 3.0;
                $setbackActive = true;
            } else {
                $setbackMinutes = 0;
                $deltaC = 0.0;
                $setbackActive = false;
            }
        }

        // Hemat energi = menit setback * daya AC (W) * faktor hemat (bps) / (60 * 10.000)
        $energySavedWh = intdiv($setbackMinutes * $acPowerWatt * $savingFactorBps, 60 * 10000);

        $certificate = new HvacSetbackCertificate(
            isOccupiedRoom: $isOccupiedRoom,
            setbackActive: $setbackActive,
            setbackMinutes: $setbackMinutes,
            energySavedWh: $energySavedWh,
            acPowerWatt: $acPowerWatt,
            savingFactorBps: $savingFactorBps,
            timestamp: time()
        );

        HvacSetbackCertificateVerifier::verify($certificate);

        return [
            'setback_active' => $setbackActive,
            'setback_temp_delta_c' => $deltaC,
            'setback_minutes' => $setbackMinutes,
            'energy_saved_wh' => $energySavedWh,
            'certificate' => $certificate,
        ];
    }
}
