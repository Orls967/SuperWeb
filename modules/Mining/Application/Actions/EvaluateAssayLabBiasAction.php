<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MiningAssayComparison;

class EvaluateAssayLabBiasAction
{
    /**
     * Evaluasi bias laboratorium internal vs umpire laboratory bersertifikat KAN (D94-2).
     * Memicu investigasi sengketa jika deviasi melebihi batas toleransi yang disepakati.
     *
     * @param array{
     *     site_id: string,
     *     lot_code: string,
     *     internal_lab_grade_ppm: int,
     *     umpire_lab_grade_ppm: int,
     *     tolerance_ppm: int
     * } $data
     * @return array{
     *     lot_code: string,
     *     internal_lab_grade_ppm: int,
     *     umpire_lab_grade_ppm: int,
     *     bias_ppm: int,
     *     tolerance_ppm: int,
     *     investigation_triggered: bool
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $lotCode = (string) $data['lot_code'];
            $internalGrade = (int) $data['internal_lab_grade_ppm'];
            $umpireGrade = (int) $data['umpire_lab_grade_ppm'];
            $tolerance = (int) $data['tolerance_ppm'];

            $biasPpm = $internalGrade - $umpireGrade;
            $investigationTriggered = abs($biasPpm) > $tolerance;

            MiningAssayComparison::updateOrCreate(
                ['lot_number' => $lotCode],
                [
                    'id' => (string) Str::uuid(),
                    'site_id' => $siteId,
                    'lab_assay_ni_ppm' => $internalGrade,
                    'lab_assay_fe_ppm' => 0,
                    'lab_assay_moisture_permil' => 0,
                    'umpire_assay_ni_ppm' => $umpireGrade,
                    'umpire_assay_fe_ppm' => 0,
                    'umpire_assay_moisture_permil' => 0,
                    'bias_ni_ppm' => $biasPpm,
                    'is_disputed' => $investigationTriggered,
                    'dispute_status' => $investigationTriggered ? 'investigating' : 'settled',
                    'resolution_notes' => $investigationTriggered
                        ? "Deviasi kadar {$biasPpm} ppm melampaui ambang toleransi {$tolerance} ppm. Prosedur arbitrase assay diaktifkan."
                        : "Kadar dalam batas toleransi ({$biasPpm} ppm <= {$tolerance} ppm).",
                ]
            );

            return [
                'lot_code' => $lotCode,
                'internal_lab_grade_ppm' => $internalGrade,
                'umpire_lab_grade_ppm' => $umpireGrade,
                'bias_ppm' => $biasPpm,
                'tolerance_ppm' => $tolerance,
                'investigation_triggered' => $investigationTriggered,
            ];
        });
    }
}
