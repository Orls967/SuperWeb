<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Enums\GeotechIncidentStatus;
use Modules\Mining\Domain\Models\MiningGeotechIncident;

/**
 * Domain Action: Ingest Data Telemetri Kestabilan Lereng Tambang (Slope Radar & InSAR).
 * Menerima stream sensor radar lereng (SSR), prisma robotik, ekstensometer retakan,
 * dan piezometer air pori untuk mendeteksi pergerakan massa lereng sedini mungkin.
 */
class IngestSlopeStabilityTelemetryAction
{
    /**
     * @param  array{
     *     incident_code?: string,
     *     location_sector: string,
     *     radar_unit_id: string,
     *     displacement_rate_mm_hour: float,
     *     cumulative_displacement_mm: float,
     *     factor_of_safety_fos?: float|null,
     *     pore_water_pressure_kpa?: float|null,
     *     crack_aperture_mm?: float|null,
     *     latitude: float,
     *     longitude: float,
     *     source_sensor_type?: string
     * } $data
     */
    public function execute(array $data): MiningGeotechIncident
    {
        $this->validateData($data);

        $dispRate = (float) $data['displacement_rate_mm_hour'];
        $cumDisp = (float) $data['cumulative_displacement_mm'];
        $fos = isset($data['factor_of_safety_fos']) ? (float) $data['factor_of_safety_fos'] : null;

        // Klasifikasi tingkat keparahan (severity) berdasarkan laju deformasi lereng:
        // Standar geoteknik lereng tambang (Read & Stacey, 2009):
        // < 1.0 mm/jam: REGULAR (Pergerakan normal/stabil)
        // 1.0 - 5.0 mm/jam: ADVISORY / REGULAR (Percepatan deformasi moderat)
        // 5.0 - 15.0 mm/jam: WARNING (Kritis, batasi akses bench)
        // > 15.0 mm/jam: CRITICAL (Potensi longsor runtuh dalam hitungan jam)
        $severity = 'LOW';
        $status = GeotechIncidentStatus::MONITORING_NORMAL;

        if ($dispRate >= 15.0 || ($fos !== null && $fos < 1.05)) {
            $severity = 'CRITICAL';
            $status = GeotechIncidentStatus::AREA_EVACUATED;
        } elseif ($dispRate >= 5.0 || ($fos !== null && $fos < 1.20)) {
            $severity = 'HIGH';
            $status = GeotechIncidentStatus::INSPECTED_BY_GEOTECH;
        } elseif ($dispRate >= 1.0 || ($fos !== null && $fos < 1.35)) {
            $severity = 'MEDIUM';
            $status = GeotechIncidentStatus::THRESHOLD_EXCEEDED;
        }

        $code = ! empty($data['incident_code'])
            ? strtoupper(trim($data['incident_code']))
            : 'GEO-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid('', true)), 0, 6));

        $incident = new MiningGeotechIncident;
        $incident->fill([
            'incident_code' => $code,
            'location_sector' => trim($data['location_sector']),
            'severity_level' => $severity,
            'factor_of_safety_fos' => $fos,
            'displacement_rate_mm_hour' => $dispRate,
            'cumulative_displacement_mm' => $cumDisp,
            'sensor_type' => $data['source_sensor_type'] ?? 'RADAR_SAR_INTERFEROMETRY',
            'status' => $status->value,
            'is_evacuation_ordered' => $status === GeotechIncidentStatus::AREA_EVACUATED,
            'pore_water_pressure_kpa' => (float) ($data['pore_water_pressure_kpa'] ?? 0.0),
            'crack_aperture_mm' => (float) ($data['crack_aperture_mm'] ?? 0.0),
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'notes' => "Telemetry ingested from {$data['radar_unit_id']} with displacement rate {$dispRate} mm/h.",
            'metadata' => [
                'radar_unit_id' => $data['radar_unit_id'],
                'severity_assessment' => $severity,
                'ingested_at' => now()->toIso8601String(),
            ],
        ]);

        $incident->save();

        return $incident;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['location_sector'])) {
            throw new InvalidArgumentException('Sektor lokasi lereng tambang harus diisi.');
        }

        if (empty($data['radar_unit_id'])) {
            throw new InvalidArgumentException('ID unit sensor / radar lereng harus disertakan.');
        }

        if (! isset($data['latitude'], $data['longitude'])) {
            throw new InvalidArgumentException('Koordinat latitude dan longitude titik pantau lereng wajib diisi.');
        }
    }
}
