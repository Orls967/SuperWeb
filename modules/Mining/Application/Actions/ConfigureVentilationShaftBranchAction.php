<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningVentilationShaft;

/**
 * Domain Action: Konfigurasi dan Kalibrasi Cabang Shaft / Airway Ventilasi Bawah Tanah.
 * Menghitung luas penampang, keliling basah (perimeter), faktor friksi Atkinson (k),
 * resistansi aerodinamika (R), dan kapasitas aliran udara maksimum.
 */
class ConfigureVentilationShaftBranchAction
{
    /**
     * @param  array{
     *     shaft_code: string,
     *     shaft_type: string,
     *     depth_meters: float,
     *     diameter_meters?: float|null,
     *     cross_section_area_m2?: float|null,
     *     perimeter_meters?: float|null,
     *     atkinson_friction_factor?: float|null,
     *     fan_installed_power_kw?: float|null,
     *     fan_rated_airflow_m3_s?: float|null,
     *     fan_operating_pressure_pa?: float|null,
     *     level_connected?: string|null,
     *     airway_shape?: string
     * } $data
     */
    public function execute(array $data): MiningVentilationShaft
    {
        $this->validateData($data);

        $shape = $data['airway_shape'] ?? 'CIRCULAR';
        $depth = (float) $data['depth_meters'];

        $area = 0.0;
        $perimeter = 0.0;

        if ($shape === 'CIRCULAR') {
            $diameter = (float) ($data['diameter_meters'] ?? 4.0);
            $radius = $diameter / 2.0;
            $area = round(pi() * ($radius ** 2), 3);
            $perimeter = round(pi() * $diameter, 3);
        } else {
            $area = (float) ($data['cross_section_area_m2'] ?? 16.0);
            $perimeter = (float) ($data['perimeter_meters'] ?? 16.0);
        }

        // Faktor friksi Atkinson baku: k (kg/m3 * 10^-3)
        // Dinding beton halus ~ 0.0035, batuan kasar tembak ~ 0.0120, kayu/baja ~ 0.0070
        $frictionFactor = (float) ($data['atkinson_friction_factor'] ?? 0.0040);

        // Resistansi Atkinson: R = (k * Per * L) / (A^3) [Ns^2 / m^8]
        $atkinsonResistance = $area > 0
            ? round(($frictionFactor * $perimeter * $depth) / ($area ** 3), 6)
            : 0.050;

        $shaft = MiningVentilationShaft::query()->firstOrNew(['shaft_code' => strtoupper(trim($data['shaft_code']))]);

        $shaft->fill([
            'shaft_type' => strtoupper(trim($data['shaft_type'])),
            'depth_meters' => $depth,
            'diameter_meters' => $data['diameter_meters'] ?? null,
            'cross_section_area_m2' => $area,
            'fan_installed_power_kw' => (float) ($data['fan_installed_power_kw'] ?? 0.0),
            'fan_rated_airflow_m3_s' => (float) ($data['fan_rated_airflow_m3_s'] ?? 0.0),
            'fan_operating_pressure_pa' => (float) ($data['fan_operating_pressure_pa'] ?? 0.0),
            'status' => 'OPERATIONAL',
            'level_connected' => $data['level_connected'] ?? 'LEVEL_ALL',
            'metadata' => [
                'airway_shape' => $shape,
                'perimeter_meters' => $perimeter,
                'atkinson_friction_k' => $frictionFactor,
                'calculated_atkinson_resistance_r' => $atkinsonResistance,
                'configured_at' => now()->toIso8601String(),
            ],
        ]);

        $shaft->save();

        return $shaft;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['shaft_code'])) {
            throw new InvalidArgumentException('Shaft code tidak boleh kosong.');
        }

        $validTypes = ['INTAKE', 'RETURN', 'ESCAPEWAY', 'INTERNAL_WINZE', 'RAISE_BORE'];
        if (! in_array(strtoupper($data['shaft_type'] ?? ''), $validTypes, true)) {
            throw new InvalidArgumentException('Tipe shaft harus salah satu dari: '.implode(', ', $validTypes));
        }

        if (($data['depth_meters'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Kedalaman shaft (depth) harus lebih besar dari 0 meter.');
        }
    }
}
