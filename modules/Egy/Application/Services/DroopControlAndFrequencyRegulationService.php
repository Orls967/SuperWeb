<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use InvalidArgumentException;
use Modules\Egy\Application\DTOs\DroopRegulationTelemetryDTO;
use Modules\Egy\Domain\Models\Microgrid;

/**
 * Layanan Pengaturan Frekuensi Primer & Sekunder Microgrid (P-f Droop Control & AGC).
 * Mengatur injeksi daya aktif inverter baterai dan generator untuk menjaga kestabilan frekuensi 50.00 Hz (50.000 mHz).
 */
final class DroopControlAndFrequencyRegulationService
{
    public const NOMINAL_FREQUENCY_MHZ = 50000; // 50.00 Hz

    public const NOMINAL_VOLTAGE_VOLT = 20000;   // 20.000 V

    public const ROCOF_TRIP_THRESHOLD_MHZ_PER_SEC = 1000; // 1.0 Hz/s trip limit

    /**
     * Hitung respons droop terhadap deviasi frekuensi dan evaluasi pemicu Loss-of-Mains.
     *
     * @param  int  $measuredFrequencyMhz  Frekuensi terukur dalam mHz (misal 49.800 mHz = 49.80 Hz)
     * @param  int  $rocofMhzPerSecond  Laju perubahan frekuensi (Rate of Change of Frequency)
     * @param  int  $measuredVoltageVolt  Tegangan busbar terukur dalam Volt
     * @param  int  $droopPercentageBps  Karakteristik droop inverter baterai (misal 400 bps = 4% droop)
     */
    public function regulateFrequency(
        string $microgridCode,
        int $measuredFrequencyMhz,
        int $rocofMhzPerSecond,
        int $measuredVoltageVolt = 20000,
        int $droopPercentageBps = 400
    ): DroopRegulationTelemetryDTO {
        $microgrid = Microgrid::where('microgrid_code', $microgridCode)->firstOrFail();

        if ($measuredFrequencyMhz < 45000 || $measuredFrequencyMhz > 55000) {
            throw new InvalidArgumentException('Frekuensi grid berada di luar rentang pengukuran aman.');
        }

        $freqDeviationMhz = self::NOMINAL_FREQUENCY_MHZ - $measuredFrequencyMhz;
        $totalCapacityKw = (int) $microgrid->total_capacity_kw;

        // Evaluasi relay proteksi Loss-of-Mains (RoCoF relay R-81)
        $isLossOfMainsTripped = abs($rocofMhzPerSecond) >= self::ROCOF_TRIP_THRESHOLD_MHZ_PER_SEC;

        // Rumus P-f Droop:
        // Delta P (kW) = (Delta f / (f_nominal * droop)) * P_nominal
        // Delta P = (freqDeviationMhz * 10000 * totalCapacityKw) / (NOMINAL_FREQ * droopPercentageBps)
        $denominator = (int) intdiv(self::NOMINAL_FREQUENCY_MHZ * $droopPercentageBps, 10000);
        $deltaActivePowerKw = (int) intdiv($freqDeviationMhz * $totalCapacityKw, $denominator);

        // Daya aktif target = daya dasar saat ini + kompensasi droop
        $baseLoadKw = (int) $microgrid->current_load_kw;
        $targetActivePowerKw = max(0, min($totalCapacityKw, $baseLoadKw + $deltaActivePowerKw));

        // Regulasi tegangan reaktif Q-V droop (2% droop voltage)
        $voltDeviationVolt = self::NOMINAL_VOLTAGE_VOLT - $measuredVoltageVolt;
        $reactiveKvar = (int) intdiv($voltDeviationVolt * $totalCapacityKw, 4000);

        // Alokasi kontribusi pembangkit
        $bessContributionKw = (int) intdiv($targetActivePowerKw * 60, 100); // 60% respons cepat BESS
        $gensetContributionKw = $targetActivePowerKw - $bessContributionKw; // 40% genset

        // Perbarui telemetri microgrid
        $microgrid->grid_frequency_mhz = $measuredFrequencyMhz;
        $microgrid->bus_voltage_volt = $measuredVoltageVolt;
        $microgrid->current_generation_kw = $targetActivePowerKw;
        $microgrid->save();

        $hashPayload = sprintf(
            'DROOP-REG|MG:%s|F_MHZ:%d|DEV:%d|KW:%d|ROCOF:%d|TRIP:%d|V:%d|Q:%d',
            $microgrid->microgrid_code,
            $measuredFrequencyMhz,
            $freqDeviationMhz,
            $targetActivePowerKw,
            $rocofMhzPerSecond,
            $isLossOfMainsTripped ? 1 : 0,
            $measuredVoltageVolt,
            $reactiveKvar
        );
        $telemetryHash = hash('sha256', $hashPayload);

        return new DroopRegulationTelemetryDTO(
            microgridCode: $microgrid->microgrid_code,
            measuredFrequencyMhz: $measuredFrequencyMhz,
            frequencyDeviationMhz: $freqDeviationMhz,
            targetActivePowerOutputKw: $targetActivePowerKw,
            rocofMhzPerSecond: $rocofMhzPerSecond,
            isLossOfMainsTripped: $isLossOfMainsTripped,
            measuredVoltageVolt: $measuredVoltageVolt,
            reactivePowerContributionKvar: $reactiveKvar,
            generatorPowerContributionsKw: [
                'bess_fast_frequency_kw' => $bessContributionKw,
                'genset_spinning_reserve_kw' => $gensetContributionKw,
            ],
            telemetryHash: $telemetryHash
        );
    }
}
