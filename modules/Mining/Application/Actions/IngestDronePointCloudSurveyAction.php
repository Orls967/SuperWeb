<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningQuarryDroneSurvey;

/**
 * Domain Action: Ingest dan Validasi Data Survei Drone / LIDAR Kuari Tambang.
 * Memverifikasi ketelitian geospasial RTK/PPK, Ground Control Points (GCP),
 * Ground Sampling Distance (GSD), dan densitas point cloud per meter persegi.
 */
class IngestDronePointCloudSurveyAction
{
    /**
     * @param  array{
     *     survey_code: string,
     *     quarry_pit_id: string,
     *     survey_type: string,
     *     drone_model: string,
     *     pilot_license_number: string,
     *     ground_control_points_count: int,
     *     horizontal_accuracy_cm: float,
     *     vertical_accuracy_cm: float,
     *     ground_sampling_distance_cm_px: float,
     *     total_points_count: int,
     *     surveyed_area_hectares: float,
     *     flight_altitude_meters: float,
     *     surveyed_at?: string
     * } $data
     */
    public function execute(array $data): MiningQuarryDroneSurvey
    {
        $this->validateData($data);

        $hAcc = (float) $data['horizontal_accuracy_cm'];
        $vAcc = (float) $data['vertical_accuracy_cm'];
        $gcpCount = (int) $data['ground_control_points_count'];

        // Kriteria ketelitian peta ortofoto & DSM tambang (BIG / ASPRS Kelas 1):
        // Horizontal accuracy <= 5.0 cm, Vertical accuracy <= 8.0 cm, GCP minimal 5 titik
        $isAsprsCompliant = $hAcc <= 5.0 && $vAcc <= 8.0 && $gcpCount >= 5;

        $survey = new MiningQuarryDroneSurvey;
        $survey->fill([
            'survey_code' => strtoupper(trim($data['survey_code'])),
            'quarry_pit_id' => trim($data['quarry_pit_id']),
            'survey_type' => strtoupper(trim($data['survey_type'])),
            'drone_model' => trim($data['drone_model']),
            'pilot_license_number' => trim($data['pilot_license_number']),
            'ground_control_points_count' => $gcpCount,
            'horizontal_accuracy_cm' => $hAcc,
            'vertical_accuracy_cm' => $vAcc,
            'ground_sampling_distance_cm_px' => (float) $data['ground_sampling_distance_cm_px'],
            'total_points_count' => (int) $data['total_points_count'],
            'surveyed_area_hectares' => (float) $data['surveyed_area_hectares'],
            'status' => $isAsprsCompliant ? 'INGESTED' : 'PRECISION_WARNING',
            'surveyed_at' => ! empty($data['surveyed_at']) ? $data['surveyed_at'] : now(),
            'metadata' => [
                'flight_altitude_meters' => (float) ($data['flight_altitude_meters'] ?? 100.0),
                'point_density_pts_m2' => round(((int) $data['total_points_count']) / (((float) $data['surveyed_area_hectares']) * 10000.0), 1),
                'is_asprs_compliant' => $isAsprsCompliant,
                'ingested_at' => now()->toIso8601String(),
            ],
        ]);

        $survey->save();

        return $survey;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['survey_code'])) {
            throw new InvalidArgumentException('Survey code tidak boleh kosong.');
        }

        if (empty($data['quarry_pit_id'])) {
            throw new InvalidArgumentException('ID quarry / pit wajib diisi.');
        }

        if (($data['ground_control_points_count'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Jumlah Ground Control Points (GCP) harus lebih besar dari 0.');
        }

        if (($data['total_points_count'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Total point cloud harus lebih besar dari 0 titik.');
        }
    }
}
