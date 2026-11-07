<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Enums\BargeShipmentStatus;
use Modules\Mining\Domain\Models\MiningBargeDraftSurvey;

/**
 * Domain Action: Pencatatan Pembacaan Draft Survey 6 Titik Tongkang (Initial / Final).
 * Menghitung draf rata-rata kuartil (Quarter Mean Draft), trim (beda draft fwd-aft),
 * kemiringan lambung (list/heel port-starboard), dan koreksi deformasi hogging/sagging.
 */
class RecordSixPointDraftSurveyAction
{
    /**
     * @param  array{
     *     barge_code: string,
     *     survey_type: string,
     *     surveyor_name: string,
     *     surveyor_company?: string,
     *     draft_forward_port_m: float,
     *     draft_forward_starboard_m: float,
     *     draft_midship_port_m: float,
     *     draft_midship_starboard_m: float,
     *     draft_aft_port_m: float,
     *     draft_aft_starboard_m: float,
     *     water_density_measured_kg_l: float,
     *     cargo_name?: string,
     *     surveyed_at?: string
     * } $data
     */
    public function execute(array $data): MiningBargeDraftSurvey
    {
        $this->validateData($data);

        $dfp = (float) $data['draft_forward_port_m'];
        $dfs = (float) $data['draft_forward_starboard_m'];
        $dmp = (float) $data['draft_midship_port_m'];
        $dms = (float) $data['draft_midship_starboard_m'];
        $dap = (float) $data['draft_aft_port_m'];
        $das = (float) $data['draft_aft_starboard_m'];

        $meanFwd = ($dfp + $dfs) / 2.0;
        $meanMid = ($dmp + $dms) / 2.0;
        $meanAft = ($dap + $das) / 2.0;

        $apparentTrim = round($meanAft - $meanFwd, 3);
        $heelDegrees = round(($dmp - $dms) * 0.85, 2);

        // Quarter Mean Draft (Mean of Means): (Fwd + 6*Mid + Aft) / 8
        $quarterMean = round(($meanFwd + (6.0 * $meanMid) + $meanAft) / 8.0, 3);

        // Indikasi deformasi lambung tongkang:
        // Mid - (Fwd + Aft)/2 : jika positif = SAGGING, jika negatif = HOGGING
        $meanEnd = ($meanFwd + $meanAft) / 2.0;
        $deflectionM = round($meanMid - $meanEnd, 3);
        $hullCondition = $deflectionM > 0.02 ? 'SAGGING' : ($deflectionM < -0.02 ? 'HOGGING' : 'EVEN_KEEL');

        $type = strtoupper(trim($data['survey_type']));

        $survey = new MiningBargeDraftSurvey;
        $survey->fill([
            'barge_code' => strtoupper(trim($data['barge_code'])),
            'survey_code' => ! empty($data['survey_code']) ? strtoupper(trim($data['survey_code'])) : ('DS-'.strtoupper(trim($data['barge_code'])).'-'.date('Ymd-His').'-'.substr(md5(uniqid('', true)), 0, 4)),
            'survey_type' => $type,
            'draft_forward_port' => $dfp,
            'draft_forward_starboard' => $dfs,
            'draft_midship_port' => $dmp,
            'draft_midship_starboard' => $dms,
            'draft_aft_port' => $dap,
            'draft_aft_starboard' => $das,
            'water_density_kg_l' => (float) $data['water_density_measured_kg_l'],
            'status' => $type === 'INITIAL_LIGHT' ? BargeShipmentStatus::BERTHED->value : BargeShipmentStatus::LOADED->value,
            'surveyor_name' => trim($data['surveyor_name']),
            'surveyor_company' => trim($data['surveyor_company'] ?? 'PT Surveyor Indonesia'),
            'surveyed_at' => ! empty($data['surveyed_at']) ? $data['surveyed_at'] : now(),
            'metadata' => [
                'mean_forward_m' => round($meanFwd, 3),
                'mean_midship_m' => round($meanMid, 3),
                'mean_aft_m' => round($meanAft, 3),
                'apparent_trim_m' => $apparentTrim,
                'heel_degrees' => $heelDegrees,
                'quarter_mean_draft_m' => $quarterMean,
                'hull_deflection_m' => $deflectionM,
                'hull_condition' => $hullCondition,
                'cargo_name' => $data['cargo_name'] ?? 'NICKEL_ORE_SAPROLITE',
                'recorded_at' => now()->toIso8601String(),
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
        if (empty($data['barge_code'])) {
            throw new InvalidArgumentException('Barge code tidak boleh kosong.');
        }

        $validTypes = ['INITIAL_LIGHT', 'INTERMEDIATE', 'FINAL_LOADED'];
        if (! in_array(strtoupper($data['survey_type'] ?? ''), $validTypes, true)) {
            throw new InvalidArgumentException('Tipe survey harus salah satu dari: '.implode(', ', $validTypes));
        }

        if (($data['water_density_measured_kg_l'] ?? 0) < 0.98 || ($data['water_density_measured_kg_l'] ?? 0) > 1.05) {
            throw new InvalidArgumentException('Densitas air harus berada di antara 0.980 hingga 1.050 kg/L (air tawar s/d laut).');
        }

        if (empty($data['surveyor_name'])) {
            throw new InvalidArgumentException('Nama marine surveyor bersertifikat wajib diisi.');
        }
    }
}
