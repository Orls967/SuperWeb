<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use InvalidArgumentException;
use Modules\Egy\Domain\Models\Microgrid;

/**
 * Layanan Pemrosesan Telemetri SCADA Microgrid & Synchrocheck Relay (ANSI 25).
 * Memvalidasi kesesuaian sudut fasa (phase angle delta < 10 derajat),
 * deviasi tegangan (|Delta V| < 5%), dan slip frekuensi (|Delta f| < 0.1 Hz)
 * sebelum izin penutupan pemutus daya (breaker) penyambungan ulang ke grid nasional PLN.
 */
final class MicrogridScadaTelemetryProcessorService
{
    public const MAX_PERMISSIBLE_PHASE_ANGLE_DELTA_DEGREE = 10;

    public const MAX_PERMISSIBLE_VOLTAGE_DELTA_VOLT = 1000; // 5% dari 20.000 V

    public const MAX_PERMISSIBLE_SLIP_FREQUENCY_MHZ = 100;  // 0.1 Hz

    /**
     * Evaluasi synchrocheck relay sebelum breaker grid ditutup.
     *
     * @param  int  $gridVoltageVolt  Tegangan sisi grid PLN (Volt)
     * @param  int  $microgridVoltageVolt  Tegangan sisi microgrid lokal (Volt)
     * @param  int  $gridFrequencyMhz  Frekuensi sisi grid PLN (mHz)
     * @param  int  $microgridFrequencyMhz  Frekuensi sisi microgrid (mHz)
     * @param  int  $phaseAngleDeltaDegree  Selisih sudut fasa (derajat integer 0-360)
     * @return array<string, mixed>
     */
    public function evaluateSynchrocheckRelay(
        string $microgridCode,
        int $gridVoltageVolt,
        int $microgridVoltageVolt,
        int $gridFrequencyMhz,
        int $microgridFrequencyMhz,
        int $phaseAngleDeltaDegree
    ): array {
        $microgrid = Microgrid::where('microgrid_code', $microgridCode)->firstOrFail();

        if ($gridVoltageVolt <= 0 || $microgridVoltageVolt <= 0) {
            throw new InvalidArgumentException('Tegangan pengukuran kedua sisi harus > 0 Volt.');
        }

        $voltageDeltaVolt = abs($gridVoltageVolt - $microgridVoltageVolt);
        $slipFrequencyMhz = abs($gridFrequencyMhz - $microgridFrequencyMhz);

        $isVoltageMatched = $voltageDeltaVolt <= self::MAX_PERMISSIBLE_VOLTAGE_DELTA_VOLT;
        $isFrequencyMatched = $slipFrequencyMhz <= self::MAX_PERMISSIBLE_SLIP_FREQUENCY_MHZ;
        $isPhaseMatched = $phaseAngleDeltaDegree <= self::MAX_PERMISSIBLE_PHASE_ANGLE_DELTA_DEGREE;

        $canCloseInterconnectBreaker = $isVoltageMatched && $isFrequencyMatched && $isPhaseMatched;

        $syncStatus = $canCloseInterconnectBreaker ? 'SYNCHRONIZED_PERMIT_CLOSE' : 'SYNCHROCHECK_BLOCKED';

        $payload = sprintf(
            'SYNCHROCHECK|MG:%s|DV:%d|DF_MHZ:%d|D_PHASE:%d|STATUS:%s',
            $microgrid->microgrid_code,
            $voltageDeltaVolt,
            $slipFrequencyMhz,
            $phaseAngleDeltaDegree,
            $syncStatus
        );
        $syncCertificateHash = hash('sha256', $payload);

        return [
            'microgrid_code' => $microgrid->microgrid_code,
            'grid_voltage_volt' => $gridVoltageVolt,
            'microgrid_voltage_volt' => $microgridVoltageVolt,
            'voltage_delta_volt' => $voltageDeltaVolt,
            'is_voltage_matched' => $isVoltageMatched,
            'grid_frequency_mhz' => $gridFrequencyMhz,
            'microgrid_frequency_mhz' => $microgridFrequencyMhz,
            'slip_frequency_mhz' => $slipFrequencyMhz,
            'is_frequency_matched' => $isFrequencyMatched,
            'phase_angle_delta_degree' => $phaseAngleDeltaDegree,
            'is_phase_matched' => $isPhaseMatched,
            'can_close_interconnect_breaker' => $canCloseInterconnectBreaker,
            'sync_status' => $syncStatus,
            'sync_certificate_hash' => $syncCertificateHash,
        ];
    }
}
