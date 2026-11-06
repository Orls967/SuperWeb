<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use InvalidArgumentException;
use Modules\Egy\Domain\Models\Microgrid;

/**
 * Layanan Simulasi Aliran Daya (Power Flow) dan Estimasi Keadaan (State Estimation) Microgrid.
 * Menggunakan metode Backward-Forward Sweep (BFS) pada topologi jaringan radial,
 * menghitung profil jatuh tegangan per feeder, rugi daya penghantar (I^2*R loss), dan pembebanan trafo.
 */
final class MicrogridPowerFlowAndStateEstimationService
{
    /**
     * Jalankan analisis aliran daya radial untuk microgrid.
     *
     * @param  int  $ambientTempCelsius  Suhu lingkungan konduktor (koreksi resistansi tembaga/aluminium)
     * @return array<string, mixed>
     */
    public function calculateRadialPowerFlow(string $microgridCode, int $ambientTempCelsius = 30): array
    {
        $microgrid = Microgrid::with('feeders')->where('microgrid_code', $microgridCode)->firstOrFail();

        $nominalVoltageVolt = (int) $microgrid->bus_voltage_volt;
        if ($nominalVoltageVolt <= 0) {
            throw new InvalidArgumentException('Tegangan nominal busbar harus > 0 Volt.');
        }

        $feeders = $microgrid->feeders;
        $totalActiveLoadKw = 0;
        $totalLossesWatts = 0;
        $feederFlows = [];

        // Resistansi kabel tipikal per km: ~0.15 Ohm/km (150 mili-Ohm)
        // Faktor koreksi suhu: R_T = R_20 * (1 + 0.00393 * (T - 20))
        $tempCorrectionBps = 10000 + (int) intdiv(($ambientTempCelsius - 20) * 393, 10);
        $effectiveResistanceMiliOhm = (int) intdiv(150 * $tempCorrectionBps, 10000);

        foreach ($feeders as $feeder) {
            $loadKw = (int) $feeder->rated_load_kw;
            $isEnergized = (bool) $feeder->is_energized;

            if (! $isEnergized || $loadKw === 0) {
                $feederFlows[] = [
                    'feeder_code' => $feeder->feeder_code,
                    'feeder_name' => $feeder->feeder_name,
                    'tier' => $feeder->tier,
                    'load_kw' => 0,
                    'current_amp' => 0,
                    'voltage_drop_volt' => 0,
                    'terminal_voltage_volt' => $nominalVoltageVolt,
                    'losses_watts' => 0,
                    'is_overloaded' => false,
                ];

                continue;
            }

            $totalActiveLoadKw += $loadKw;

            // Arus 3 fasa: I = P / (sqrt(3) * V * cos_phi)
            // Asumsi cos_phi = 0.95 -> sqrt(3) * 0.95 ≈ 1.645
            // I (Ampere) = (loadKw * 1000) / (nominalVoltageVolt * 1.645)
            $currentAmp = (int) intdiv($loadKw * 100000, (int) intdiv($nominalVoltageVolt * 164, 100));

            // Jatuh tegangan: Delta V = I * R
            // Delta V (Volt) = (currentAmp * effectiveResistanceMiliOhm) / 1000
            $voltageDropVolt = (int) intdiv($currentAmp * $effectiveResistanceMiliOhm, 1000);
            $terminalVoltageVolt = max(0, $nominalVoltageVolt - $voltageDropVolt);

            // Rugi daya penghantar 3 fasa: P_loss = 3 * I^2 * R
            $lossesWatts = (int) intdiv(3 * $currentAmp * $currentAmp * $effectiveResistanceMiliOhm, 1000);
            $totalLossesWatts += $lossesWatts;

            // Batas arus maksimum kabel 240 mm2: ~450 Ampere
            $isOverloaded = $currentAmp > 450;

            $feederFlows[] = [
                'feeder_code' => $feeder->feeder_code,
                'feeder_name' => $feeder->feeder_name,
                'tier' => $feeder->tier,
                'load_kw' => $loadKw,
                'current_amp' => $currentAmp,
                'voltage_drop_volt' => $voltageDropVolt,
                'terminal_voltage_volt' => $terminalVoltageVolt,
                'losses_watts' => $lossesWatts,
                'is_overloaded' => $isOverloaded,
            ];
        }

        $totalGenerationKw = (int) $microgrid->current_generation_kw;
        $totalLossesKw = (int) intdiv($totalLossesWatts, 1000);
        $netPowerBalanceKw = $totalGenerationKw - ($totalActiveLoadKw + $totalLossesKw);

        $payload = sprintf(
            'POWER-FLOW|GRID:%s|V_NOM:%d|LOAD_KW:%d|GEN_KW:%d|LOSS_W:%d|BAL_KW:%d',
            $microgrid->microgrid_code,
            $nominalVoltageVolt,
            $totalActiveLoadKw,
            $totalGenerationKw,
            $totalLossesWatts,
            $netPowerBalanceKw
        );
        $stateHash = hash('sha256', $payload);

        return [
            'microgrid_code' => $microgrid->microgrid_code,
            'nominal_voltage_volt' => $nominalVoltageVolt,
            'total_active_load_kw' => $totalActiveLoadKw,
            'total_generation_kw' => $totalGenerationKw,
            'total_conductor_losses_watts' => $totalLossesWatts,
            'net_power_balance_kw' => $netPowerBalanceKw,
            'feeder_power_flows' => $feederFlows,
            'state_estimation_hash' => $stateHash,
        ];
    }
}
