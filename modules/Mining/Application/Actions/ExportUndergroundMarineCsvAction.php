<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Models\MiningBargeDraftSurvey;
use Modules\Mining\Domain\Models\MiningBlastClearanceRun;
use Modules\Mining\Domain\Models\MiningGeotechIncident;
use Modules\Mining\Domain\Models\MiningQuarryDroneSurvey;
use Modules\Mining\Domain\Models\MiningUndergroundStope;

/**
 * Domain Action: Ekspor Laporan CSV Audit Terpadu Operasi Bawah Tanah, Peledakan, & Pengapalan Tongkang.
 * Menyusun data ringkasan ke format CSV terstandarisasi untuk inspektur tambang Ditjen Minerba ESDM.
 */
class ExportUndergroundMarineCsvAction
{
    /**
     * @return array{
     *     filename: string,
     *     csv_content: string,
     *     row_count: int
     * }
     */
    public function execute(string $domain = 'ALL'): array
    {
        $domain = strtoupper(trim($domain));
        $csvLines = [];

        if ($domain === 'ALL' || $domain === 'UNDERGROUND') {
            $csvLines[] = '# DOMAIN: UNDERGROUND STOPES & VENTILATION';
            $csvLines[] = 'Stope Code,Level,Method,Status,Planned Tonnage (t),Actual Tonnage (t),Ore Grade (%),RMR,Volume (m3)';
            $stopes = MiningUndergroundStope::query()->latest()->limit(100)->get();
            foreach ($stopes as $stope) {
                $csvLines[] = implode(',', [
                    $stope->stope_code,
                    '"'.$stope->level_name.'"',
                    $stope->mining_method,
                    $stope->status,
                    $stope->planned_tonnage,
                    $stope->actual_tonnage,
                    $stope->ore_grade,
                    $stope->rock_mass_rating,
                    $stope->volume_m3,
                ]);
            }
            $csvLines[] = '';
        }

        if ($domain === 'ALL' || $domain === 'BLASTING') {
            $csvLines[] = '# DOMAIN: BLASTING OPERATIONS & SAFETY CLEARANCE';
            $csvLines[] = 'Run Code,Pattern ID,Status,Safety Radius (m),Clearance Verified,Siren Activated At,Detonated At';
            $runs = MiningBlastClearanceRun::query()->latest()->limit(100)->get();
            foreach ($runs as $run) {
                $csvLines[] = implode(',', [
                    $run->run_code,
                    $run->pattern_id,
                    $run->status,
                    $run->safety_radius_meters,
                    $run->all_personnel_evacuated ? 'YES' : 'NO',
                    $run->siren_activated_at ? $run->siren_activated_at->toIso8601String() : 'N/A',
                    $run->detonated_at ? $run->detonated_at->toIso8601String() : 'N/A',
                ]);
            }
            $csvLines[] = '';
        }

        if ($domain === 'ALL' || $domain === 'GEOTECH') {
            $csvLines[] = '# DOMAIN: GEOTECH & SLOPE STABILITY';
            $csvLines[] = 'Incident Code,Location Sector,Severity,Status,FoS,Disp Rate (mm/h),Cum Disp (mm),Evacuated';
            $incidents = MiningGeotechIncident::query()->latest()->limit(100)->get();
            foreach ($incidents as $inc) {
                $csvLines[] = implode(',', [
                    $inc->incident_code,
                    '"'.$inc->location_sector.'"',
                    $inc->severity_level,
                    $inc->status,
                    $inc->factor_of_safety_fos ?? 'N/A',
                    $inc->displacement_rate_mm_hour,
                    $inc->cumulative_displacement_mm,
                    $inc->is_evacuation_ordered ? 'YES' : 'NO',
                ]);
            }
            $csvLines[] = '';
        }

        if ($domain === 'ALL' || $domain === 'DRONE_QUARRY') {
            $csvLines[] = '# DOMAIN: QUARRY DRONE SURVEYS & VOLUMETRICS';
            $csvLines[] = 'Survey Code,Quarry Pit,Survey Type,Net Volume (m3),Tonnage WMT,Status,Surveyed At';
            $surveys = MiningQuarryDroneSurvey::query()->latest()->limit(100)->get();
            foreach ($surveys as $surv) {
                $csvLines[] = implode(',', [
                    $surv->survey_code,
                    $surv->quarry_pit_id,
                    $surv->survey_type,
                    $surv->calculated_net_volume_m3 ?? 0.0,
                    $surv->calculated_tonnage_wmt ?? 0.0,
                    $surv->status,
                    $surv->surveyed_at ? $surv->surveyed_at->toIso8601String() : 'N/A',
                ]);
            }
            $csvLines[] = '';
        }

        if ($domain === 'ALL' || $domain === 'BARGE_MARINE') {
            $csvLines[] = '# DOMAIN: BARGE DRAFT SURVEY & MARINE SHIPMENTS';
            $csvLines[] = 'Barge Code,Survey Type,Status,Cargo WMT,Displacement MT,Surveyor,Water Density (kg/L)';
            $barges = MiningBargeDraftSurvey::query()->latest()->limit(100)->get();
            foreach ($barges as $b) {
                $csvLines[] = implode(',', [
                    $b->barge_code,
                    $b->survey_type,
                    $b->status,
                    $b->cargo_tonnage_wmt ?? 0.0,
                    $b->displacement_tonnage_mt ?? 0.0,
                    '"'.$b->surveyor_name.'"',
                    $b->water_density_kg_l,
                ]);
            }
        }

        $content = implode("\n", $csvLines);
        $filename = 'mining_underground_marine_report_'.strtolower($domain).'_'.date('Ymd_His').'.csv';

        return [
            'filename' => $filename,
            'csv_content' => $content,
            'row_count' => count($csvLines),
        ];
    }
}
