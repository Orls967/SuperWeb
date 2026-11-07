<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningQuarryDroneSurvey;

/**
 * Domain Action: Kalkulasi Volumetrik Timbunan / Galian via TIN (Triangulated Irregular Network).
 * Menghitung selisih prisma segitiga antara permukaan topografi dasar (base surface)
 * dan permukaan atas timbunan drone (upper surface), menghasilkan volume m3 dan tonase.
 */
class CalculateTinMeshStockpileVolumeAction
{
    /**
     * @param  array{
     *     drone_survey_id: string,
     *     stockpile_identifier: string,
     *     material_type: string,
     *     bulk_density_t_m3: float,
     *     triangles_mesh: list<array{
     *         area_m2: float,
     *         delta_z1: float,
     *         delta_z2: float,
     *         delta_z3: float
     *     }>,
     *     moisture_percentage?: float
     * } $data
     * @return array{
     *     survey_code: string,
     *     stockpile_identifier: string,
     *     material_type: string,
     *     cut_volume_m3: float,
     *     fill_volume_m3: float,
     *     net_volume_m3: float,
     *     wet_metric_tonnage_wmt: float,
     *     dry_metric_tonnage_dmt: float,
     *     triangles_evaluated: int
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $survey = MiningQuarryDroneSurvey::query()->findOrFail($data['drone_survey_id']);

        $density = (float) $data['bulk_density_t_m3'];
        $moisture = (float) ($data['moisture_percentage'] ?? 8.0);

        $cutVolume = 0.0;
        $fillVolume = 0.0;
        $trianglesCount = count($data['triangles_mesh']);

        foreach ($data['triangles_mesh'] as $tri) {
            $area = (float) $tri['area_m2'];
            $avgHeightDelta = ((float) $tri['delta_z1'] + (float) $tri['delta_z2'] + (float) $tri['delta_z3']) / 3.0;
            $prismVolume = $area * $avgHeightDelta;

            if ($prismVolume >= 0) {
                $fillVolume += $prismVolume; // Timbunan di atas base surface
            } else {
                $cutVolume += abs($prismVolume); // Galian di bawah base surface
            }
        }

        $cutVolume = round($cutVolume, 2);
        $fillVolume = round($fillVolume, 2);
        $netVolume = round($fillVolume - $cutVolume, 2);

        $wmt = round($netVolume * $density, 2);
        $dmt = round($wmt * (1.0 - ($moisture / 100.0)), 2);

        // Update total volume pada survey record jika survei mencakup pit keseluruhan
        $survey->calculated_cut_volume_m3 = round(($survey->calculated_cut_volume_m3 ?? 0.0) + $cutVolume, 2);
        $survey->calculated_fill_volume_m3 = round(($survey->calculated_fill_volume_m3 ?? 0.0) + $fillVolume, 2);
        $survey->calculated_net_volume_m3 = round(($survey->calculated_net_volume_m3 ?? 0.0) + $netVolume, 2);
        $survey->calculated_tonnage_wmt = round(($survey->calculated_tonnage_wmt ?? 0.0) + $wmt, 2);
        $survey->status = 'VOLUMETRIC_COMPLETED';

        $meta = $survey->metadata ?? [];
        $meta['stockpile_calculations'][$data['stockpile_identifier']] = [
            'material' => $data['material_type'],
            'density_t_m3' => $density,
            'moisture_pct' => $moisture,
            'cut_m3' => $cutVolume,
            'fill_m3' => $fillVolume,
            'net_m3' => $netVolume,
            'tonnage_wmt' => $wmt,
            'tonnage_dmt' => $dmt,
            'triangles_count' => $trianglesCount,
            'calculated_at' => now()->toIso8601String(),
        ];
        $survey->metadata = $meta;
        $survey->save();

        return [
            'survey_code' => $survey->survey_code,
            'stockpile_identifier' => $data['stockpile_identifier'],
            'material_type' => $data['material_type'],
            'cut_volume_m3' => $cutVolume,
            'fill_volume_m3' => $fillVolume,
            'net_volume_m3' => $netVolume,
            'wet_metric_tonnage_wmt' => $wmt,
            'dry_metric_tonnage_dmt' => $dmt,
            'triangles_evaluated' => $trianglesCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['drone_survey_id'])) {
            throw new InvalidArgumentException('Drone survey ID wajib diisi.');
        }

        if (empty($data['stockpile_identifier'])) {
            throw new InvalidArgumentException('Pengidentifikasi timbunan (stockpile ID) wajib diisi.');
        }

        if (($data['bulk_density_t_m3'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Densitas ruah material (bulk density) harus lebih besar dari 0 t/m3.');
        }

        if (empty($data['triangles_mesh']) || ! is_array($data['triangles_mesh'])) {
            throw new InvalidArgumentException('Mesh TIN segitiga harus disertakan.');
        }
    }
}
