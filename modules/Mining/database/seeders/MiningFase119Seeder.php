<?php

declare(strict_types=1);

namespace Modules\Mining\database\seeders;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Modules\Mining\Application\Actions\ConfigureVentilationShaftBranchAction;
use Modules\Mining\Application\Actions\CreateUndergroundStopeAction;
use Modules\Mining\Application\Actions\EvaluateMuckpileKcoFragmentationAction;
use Modules\Mining\Application\Actions\IngestDronePointCloudSurveyAction;
use Modules\Mining\Application\Actions\IngestSlopeStabilityTelemetryAction;
use Modules\Mining\Application\Actions\RecordSixPointDraftSurveyAction;
use Modules\Mining\Application\Actions\SimulateHardyCrossVentilationNetworkAction;
use Modules\Mining\Domain\Enums\BargeShipmentStatus;
use Modules\Mining\Domain\Enums\BlastClearanceStatus;
use Modules\Mining\Domain\Enums\GeotechIncidentStatus;
use Modules\Mining\Domain\Models\MiningBargeDraftSurvey;
use Modules\Mining\Domain\Models\MiningBlastClearanceRun;
use Modules\Mining\Domain\Models\MiningBlastFragmentationEval;
use Modules\Mining\Domain\Models\MiningGeotechIncident;
use Modules\Mining\Domain\Models\MiningQuarryDroneSurvey;
use Modules\Mining\Domain\Models\MiningUndergroundStope;
use Modules\Mining\Domain\Models\MiningVentilationSimulation;

/**
 * Seeder T1 Kaya Modul Pertambangan Fase 119 (Standar W8).
 * Menginisialisasi 16 skenario bernama idempoten mencakup:
 * - Stope Bawah Tanah & RMR Geomekanika
 * - Shaft Intake/Return & Jaringan Ventilasi Hardy-Cross
 * - Siklus Peledakan, Geofence Telematika & Model Fragmentasi KCO
 * - Radar InSAR Deformasi Lereng & Bishop FoS
 * - Survei Drone Kuari & Volumetrik Mesh TIN
 * - Draft Survey 6-Titik Tongkang & Pelunasan Freight Ledger
 */
class MiningFase119Seeder extends Seeder
{
    public function run(): void
    {
        // Pastikan akun buku besar terdaftar
        $this->call(LedgerAccountsSeeder::class);

        $this->seedUndergroundStopes();
        $this->seedVentilationShafts();
        $this->seedVentilationSimulations();
        $this->seedBlastingClearanceAndKco();
        $this->seedGeotechAndSlopeRadar();
        $this->seedQuarryDroneSurveys();
        $this->seedMarineBargeDraftSurveys();
    }

    private function seedUndergroundStopes(): void
    {
        $action = app(CreateUndergroundStopeAction::class);

        // Skenario 1: STP-L250-SAP-01 (Sublevel Open Stoping, RMR 78)
        if (! MiningUndergroundStope::query()->where('stope_code', 'STP-L250-SAP-01')->exists()) {
            $action->execute([
                'stope_code' => 'STP-L250-SAP-01',
                'level_name' => 'Level -250m RL Hanging Wall Drift',
                'mining_method' => 'SUBLEVEL_OPEN_STOPING',
                'planned_tonnage' => 38000.0,
                'ore_grade' => 2.15,
                'rock_mass_rating' => 78,
                'length_meters' => 50.0,
                'width_meters' => 18.0,
                'height_meters' => 30.0,
                'rock_density_t_m3' => 2.65,
                'support_system' => 'SYSTEMATIC_BOLTS_2M_SPACING_WITH_MESH',
            ]);
        }

        // Skenario 2: STP-L350-SAP-02 (Cut-and-Fill Stoping, RMR 55)
        if (! MiningUndergroundStope::query()->where('stope_code', 'STP-L350-SAP-02')->exists()) {
            $action->execute([
                'stope_code' => 'STP-L350-SAP-02',
                'level_name' => 'Level -350m RL Stope Heading 4',
                'mining_method' => 'CUT_AND_FILL_STOPING',
                'planned_tonnage' => 28500.0,
                'ore_grade' => 2.45,
                'rock_mass_rating' => 55,
                'length_meters' => 45.0,
                'width_meters' => 15.0,
                'height_meters' => 25.0,
                'rock_density_t_m3' => 2.70,
                'support_system' => 'SYSTEMATIC_BOLTS_1_5M_SPACING_FIBER_SHOTCRETE_50MM',
            ]);
        }

        // Skenario 3: STP-L450-LIM-03 (Deep Stope, RMR 68)
        if (! MiningUndergroundStope::query()->where('stope_code', 'STP-L450-LIM-03')->exists()) {
            $action->execute([
                'stope_code' => 'STP-L450-LIM-03',
                'level_name' => 'Level -450m RL Main Ore Body',
                'mining_method' => 'SUBLEVEL_OPEN_STOPING',
                'planned_tonnage' => 52000.0,
                'ore_grade' => 1.95,
                'rock_mass_rating' => 68,
                'length_meters' => 65.0,
                'width_meters' => 22.0,
                'height_meters' => 35.0,
                'rock_density_t_m3' => 2.68,
                'support_system' => 'SYSTEMATIC_BOLTS_2M_SPACING_WITH_MESH',
            ]);
        }

        // Skenario 4: STP-L500-CU-04 (Deep Ultra High Grade, RMR 82)
        if (! MiningUndergroundStope::query()->where('stope_code', 'STP-L500-CU-04')->exists()) {
            $action->execute([
                'stope_code' => 'STP-L500-CU-04',
                'level_name' => 'Level -500m Exploration Drift Deep',
                'mining_method' => 'SUBLEVEL_OPEN_STOPING',
                'planned_tonnage' => 65000.0,
                'ore_grade' => 3.20,
                'rock_mass_rating' => 82,
                'length_meters' => 70.0,
                'width_meters' => 25.0,
                'height_meters' => 40.0,
                'rock_density_t_m3' => 2.80,
                'support_system' => 'SPOT_BOLTING_AS_REQUIRED',
            ]);
        }
    }

    private function seedVentilationShafts(): void
    {
        $action = app(ConfigureVentilationShaftBranchAction::class);

        // Skenario 5: SHF-MAIN-DOWNCAST-01
        $action->execute([
            'shaft_code' => 'SHF-MAIN-DOWNCAST-01',
            'shaft_type' => 'INTAKE',
            'depth_meters' => 450.0,
            'diameter_meters' => 5.5,
            'fan_installed_power_kw' => 550.0,
            'fan_rated_airflow_m3_s' => 220.0,
            'fan_operating_pressure_pa' => 1650.0,
            'level_connected' => 'LEVEL_-250_TO_-450',
        ]);

        // Skenario 6: SHF-EXHAUST-UPCAST-02
        $action->execute([
            'shaft_code' => 'SHF-EXHAUST-UPCAST-02',
            'shaft_type' => 'RETURN',
            'depth_meters' => 480.0,
            'diameter_meters' => 6.0,
            'fan_installed_power_kw' => 650.0,
            'fan_rated_airflow_m3_s' => 240.0,
            'fan_operating_pressure_pa' => 1780.0,
            'level_connected' => 'LEVEL_-450_TO_SURFACE',
        ]);
    }

    private function seedVentilationSimulations(): void
    {
        $action = app(SimulateHardyCrossVentilationNetworkAction::class);

        // Skenario 7: SIM-HARDY-CROSS-BENCHMARK-01
        if (! MiningVentilationSimulation::query()->where('simulation_code', 'SIM-HARDY-CROSS-BENCHMARK-01')->exists()) {
            $action->execute([
                'simulation_code' => 'SIM-HARDY-CROSS-BENCHMARK-01',
                'fan_rpm_percent' => 100.0,
                'air_density_kg_m3' => 1.20,
                'headcount_underground' => 35,
                'diesel_fleet_kw' => 480.0,
                'branches' => [
                    [
                        'id' => 'BR-IN-01',
                        'name' => 'Main Intake Downcast',
                        'resistance_r' => 0.035,
                        'initial_flow_q' => 160.0,
                        'fan_pressure_pf' => 1650.0,
                    ],
                    [
                        'id' => 'BR-WORK-STP1',
                        'name' => 'Stope L250 Working Drift',
                        'resistance_r' => 0.085,
                        'initial_flow_q' => 80.0,
                    ],
                    [
                        'id' => 'BR-WORK-STP2',
                        'name' => 'Stope L350 Working Drift',
                        'resistance_r' => 0.090,
                        'initial_flow_q' => 80.0,
                    ],
                    [
                        'id' => 'BR-RET-01',
                        'name' => 'Main Exhaust Upcast',
                        'resistance_r' => 0.040,
                        'initial_flow_q' => 160.0,
                    ],
                ],
                'loops' => [
                    [
                        'loop_id' => 'LP-NORTH-CIRCUIT',
                        'branch_ids' => ['BR-IN-01', 'BR-WORK-STP1', 'BR-RET-01'],
                        'directions' => [1, 1, -1],
                    ],
                ],
                'executed_by' => 'Senior Ventilation Engineer T1',
            ]);
        }
    }

    private function seedBlastingClearanceAndKco(): void
    {
        // Skenario 8: BLAST-RUN-PIT-NORTH-20261107 (Detonated)
        $blastNorth = MiningBlastClearanceRun::query()->firstOrNew(['run_code' => 'BLAST-RUN-PIT-NORTH-20261107']);
        $blastNorth->fill([
            'pattern_id' => 'PAT-NORTH-BENCH-12',
            'pit_name' => 'Pit North Sapphire',
            'center_latitude' => -3.421500,
            'center_longitude' => 114.598200,
            'safety_radius_meters' => 550.0,
            'planned_holes_count' => 48,
            'total_explosives_kg' => 2400.0,
            'all_personnel_evacuated' => true,
            'status' => BlastClearanceStatus::DETONATED->value,
            'siren_activated_at' => now()->subHours(2),
            'detonated_at' => now()->subHours(1),
            'clearance_notes' => 'Peledakan produksi terkendali selesai; sterilisasi perimeter 100% aman.',
        ]);
        $blastNorth->save();

        // Skenario 9: BLAST-RUN-PIT-SOUTH-BREACH (Draft dengan Pelanggaran Personel)
        $blastSouth = MiningBlastClearanceRun::query()->firstOrNew(['run_code' => 'BLAST-RUN-PIT-SOUTH-BREACH']);
        $blastSouth->fill([
            'pattern_id' => 'PAT-SOUTH-BENCH-08',
            'pit_name' => 'Pit South Emerald',
            'center_latitude' => -3.435000,
            'center_longitude' => 114.612000,
            'safety_radius_meters' => 500.0,
            'planned_holes_count' => 36,
            'total_explosives_kg' => 1800.0,
            'all_personnel_evacuated' => false,
            'status' => BlastClearanceStatus::DRAFT->value,
            'clearance_notes' => 'Tertahan: 2 personel terdeteksi di radius 380m.',
        ]);
        $blastSouth->save();

        // Skenario 10: EVAL-KCO-MUCKPILE-OPTIMAL
        $evalAction = app(EvaluateMuckpileKcoFragmentationAction::class);
        if (! MiningBlastFragmentationEval::query()->where('eval_code', 'EVAL-KCO-MUCKPILE-OPTIMAL')->exists()) {
            $evalAction->execute([
                'blast_clearance_run_id' => $blastNorth->id,
                'eval_code' => 'EVAL-KCO-MUCKPILE-OPTIMAL',
                'rock_factor_a' => 7.5,
                'burden_meters' => 3.5,
                'spacing_meters' => 4.2,
                'hole_diameter_mm' => 165.0,
                'bench_height_meters' => 12.0,
                'charge_length_meters' => 8.5,
                'explosive_rws' => 105.0,
                'explosive_density' => 1.15,
                'target_crusher_topsize_mm' => 800.0,
                'evaluator_name' => 'Drill & Blast Specialist T1',
            ]);
        }

        // Skenario 11: EVAL-KCO-MUCKPILE-BOULDERS
        if (! MiningBlastFragmentationEval::query()->where('eval_code', 'EVAL-KCO-MUCKPILE-BOULDERS')->exists()) {
            $evalAction->execute([
                'blast_clearance_run_id' => $blastNorth->id,
                'eval_code' => 'EVAL-KCO-MUCKPILE-BOULDERS',
                'rock_factor_a' => 12.0,
                'burden_meters' => 4.8,
                'spacing_meters' => 5.5,
                'hole_diameter_mm' => 165.0,
                'bench_height_meters' => 12.0,
                'charge_length_meters' => 6.0,
                'explosive_rws' => 95.0,
                'explosive_density' => 1.10,
                'target_crusher_topsize_mm' => 800.0,
                'evaluator_name' => 'Drill & Blast Specialist T1',
            ]);
        }
    }

    private function seedGeotechAndSlopeRadar(): void
    {
        $geoAction = app(IngestSlopeStabilityTelemetryAction::class);

        // Skenario 12: GEO-INC-EAST-WALL-SLOPE-CRITICAL
        if (! MiningGeotechIncident::query()->where('incident_code', 'GEO-INC-EAST-WALL-SLOPE-CRITICAL')->exists()) {
            $geoAction->execute([
                'incident_code' => 'GEO-INC-EAST-WALL-SLOPE-CRITICAL',
                'location_sector' => 'Pit North East Highwall Bench 09',
                'radar_unit_id' => 'RADAR-SSR-GROUNDPROBE-01',
                'displacement_rate_mm_hour' => 14.8,
                'cumulative_displacement_mm' => 82.5,
                'factor_of_safety_fos' => 1.02,
                'pore_water_pressure_kpa' => 185.0,
                'crack_aperture_mm' => 32.0,
                'latitude' => -3.422100,
                'longitude' => 114.599500,
                'source_sensor_type' => 'SSR_SLOPE_RADAR',
            ]);
        }

        // Skenario 13: GEO-INC-NORTH-PIT-MONITORING (Stabil Pasca-Remediasi)
        if (! MiningGeotechIncident::query()->where('incident_code', 'GEO-INC-NORTH-PIT-MONITORING')->exists()) {
            $incident = $geoAction->execute([
                'incident_code' => 'GEO-INC-NORTH-PIT-MONITORING',
                'location_sector' => 'Pit South West Toe Bench 04',
                'radar_unit_id' => 'RADAR-REUTECH-INSA-02',
                'displacement_rate_mm_hour' => 0.35,
                'cumulative_displacement_mm' => 12.4,
                'factor_of_safety_fos' => 1.45,
                'pore_water_pressure_kpa' => 35.0,
                'crack_aperture_mm' => 2.0,
                'latitude' => -3.436200,
                'longitude' => 114.613500,
                'source_sensor_type' => 'INSAR_SATELLITE_AND_RADAR',
            ]);
            $incident->status = GeotechIncidentStatus::ALL_CLEAR->value;
            $incident->is_evacuation_ordered = false;
            $incident->save();
        }
    }

    private function seedQuarryDroneSurveys(): void
    {
        $droneAction = app(IngestDronePointCloudSurveyAction::class);

        // Skenario 14: DRONE-SRV-QUARRY-BENCH-202611
        if (! MiningQuarryDroneSurvey::query()->where('survey_code', 'DRONE-SRV-QUARRY-BENCH-202611')->exists()) {
            $survey = $droneAction->execute([
                'survey_code' => 'DRONE-SRV-QUARRY-BENCH-202611',
                'quarry_pit_id' => 'QUARRY-ANDESITE-PIT-01',
                'survey_type' => 'LIDAR_POINT_CLOUD_AND_ORTHOPHOTO',
                'drone_model' => 'DJI Matrice 350 RTK Zenmuse L2',
                'pilot_license_number' => 'DKPPU-DRONE-8821-ID',
                'ground_control_points_count' => 8,
                'horizontal_accuracy_cm' => 3.2,
                'vertical_accuracy_cm' => 4.8,
                'ground_sampling_distance_cm_px' => 1.85,
                'total_points_count' => 2850000,
                'surveyed_area_hectares' => 24.5,
                'flight_altitude_meters' => 90.0,
            ]);

            $survey->calculated_cut_volume_m3 = 125000.0;
            $survey->calculated_fill_volume_m3 = 18500.0;
            $survey->calculated_net_volume_m3 = 106500.0;
            $survey->calculated_tonnage_wmt = 276900.0; // Density ~2.60
            $survey->status = 'VOLUMETRIC_COMPLETED';
            $survey->save();
        }
    }

    private function seedMarineBargeDraftSurveys(): void
    {
        $draftAction = app(RecordSixPointDraftSurveyAction::class);

        // Skenario 15: BARGE-DS-MARITIME-PRIMA-88 (Reconciled & Settle)
        if (! MiningBargeDraftSurvey::query()->where('barge_code', 'BG-MARITIME-PRIMA-88')->exists()) {
            // Initial Light
            $draftAction->execute([
                'barge_code' => 'BG-MARITIME-PRIMA-88',
                'survey_type' => 'INITIAL_LIGHT',
                'surveyor_name' => 'Bambang Sutrisno, M.Mar.Surv',
                'surveyor_company' => 'PT Surveyor Indonesia (Persero)',
                'draft_forward_port_m' => 0.85,
                'draft_forward_starboard_m' => 0.87,
                'draft_midship_port_m' => 0.88,
                'draft_midship_starboard_m' => 0.90,
                'draft_aft_port_m' => 1.25,
                'draft_aft_starboard_m' => 1.28,
                'water_density_measured_kg_l' => 1.018,
                'cargo_name' => 'NICKEL_ORE_SAPROLITE_1.85',
            ]);

            // Final Loaded
            $final = $draftAction->execute([
                'barge_code' => 'BG-MARITIME-PRIMA-88',
                'survey_type' => 'FINAL_LOADED',
                'surveyor_name' => 'Bambang Sutrisno, M.Mar.Surv',
                'surveyor_company' => 'PT Surveyor Indonesia (Persero)',
                'draft_forward_port_m' => 4.75,
                'draft_forward_starboard_m' => 4.78,
                'draft_midship_port_m' => 4.82,
                'draft_midship_starboard_m' => 4.84,
                'draft_aft_port_m' => 5.15,
                'draft_aft_starboard_m' => 5.18,
                'water_density_measured_kg_l' => 1.019,
                'cargo_name' => 'NICKEL_ORE_SAPROLITE_1.85',
            ]);

            $final->displacement_tonnage_mt = 8680.5;
            $final->cargo_tonnage_wmt = 7520.0;
            $final->reconciliation_variance_pct = 0.24;
            $final->status = BargeShipmentStatus::SETTLED->value;
            $final->save();
        }

        // Skenario 16: BARGE-DS-SELAT-MAKMUR-09 (Final Survey Completed)
        if (! MiningBargeDraftSurvey::query()->where('barge_code', 'BG-SELAT-MAKMUR-09')->exists()) {
            $draftAction->execute([
                'barge_code' => 'BG-SELAT-MAKMUR-09',
                'survey_type' => 'INITIAL_LIGHT',
                'surveyor_name' => 'Capt. Hendra Gunawan',
                'surveyor_company' => 'PT Carsurin Marine Services',
                'draft_forward_port_m' => 0.90,
                'draft_forward_starboard_m' => 0.92,
                'draft_midship_port_m' => 0.94,
                'draft_midship_starboard_m' => 0.95,
                'draft_aft_port_m' => 1.30,
                'draft_aft_starboard_m' => 1.32,
                'water_density_measured_kg_l' => 1.020,
                'cargo_name' => 'COAL_GAR_4200',
            ]);

            $final2 = $draftAction->execute([
                'barge_code' => 'BG-SELAT-MAKMUR-09',
                'survey_type' => 'FINAL_LOADED',
                'surveyor_name' => 'Capt. Hendra Gunawan',
                'surveyor_company' => 'PT Carsurin Marine Services',
                'draft_forward_port_m' => 4.90,
                'draft_forward_starboard_m' => 4.92,
                'draft_midship_port_m' => 4.98,
                'draft_midship_starboard_m' => 5.00,
                'draft_aft_port_m' => 5.35,
                'draft_aft_starboard_m' => 5.38,
                'water_density_measured_kg_l' => 1.020,
                'cargo_name' => 'COAL_GAR_4200',
            ]);

            $final2->displacement_tonnage_mt = 9120.0;
            $final2->cargo_tonnage_wmt = 7850.0;
            $final2->reconciliation_variance_pct = 2.15;
            $final2->status = BargeShipmentStatus::DRAFT_SURVEYED->value;
            $final2->save();
        }
    }
}
