<?php

declare(strict_types=1);

namespace Tests\Acceptance\MiningUnderground;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mining\Application\Actions\AuditBargeChainOfCustodyAction;
use Modules\Mining\Application\Actions\CreateUndergroundStopeAction;
use Modules\Mining\Application\Actions\EvaluateMuckpileKcoFragmentationAction;
use Modules\Mining\Application\Actions\ExecuteControlledDetonationAction;
use Modules\Mining\Application\Actions\IngestSlopeStabilityTelemetryAction;
use Modules\Mining\Application\Actions\ReconcileHydrostaticBargeTonnageAction;
use Modules\Mining\Application\Actions\RecordSixPointDraftSurveyAction;
use Modules\Mining\Application\Actions\ResolveGeotechIncidentAction;
use Modules\Mining\Application\Actions\SettleBargeFreightTariffAction;
use Modules\Mining\Application\Actions\SimulateHardyCrossVentilationNetworkAction;
use Modules\Mining\Application\Actions\TriggerBlastWarningSirenAction;
use Modules\Mining\Application\Actions\TriggerGeotechEvacuationAlarmAction;
use Modules\Mining\Application\Actions\VerifyBlastPerimeterClearanceAction;
use Modules\Mining\Domain\Enums\BargeShipmentStatus;
use Modules\Mining\Domain\Enums\BlastClearanceStatus;
use Modules\Mining\Domain\Enums\GeotechIncidentStatus;
use Modules\Mining\Domain\Models\MiningBlastClearanceRun;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Tests\TestCase;

class MiningUndergroundAndMarineAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    /**
     * Skenario 1: Desain Stope Bawah Tanah & Solver Ventilasi Hardy-Cross (M7).
     */
    public function test_underground_stope_and_hardy_cross_ventilation_cycle(): void
    {
        $stopeAction = app(CreateUndergroundStopeAction::class);
        $ventAction = app(SimulateHardyCrossVentilationNetworkAction::class);

        $stope = $stopeAction->execute([
            'stope_code' => 'STP-ACCEPTANCE-01',
            'level_name' => 'Level -300m RL Drift 1',
            'planned_tonnage' => 35000.0,
            'ore_grade' => 2.10,
            'rock_mass_rating' => 75,
            'length_meters' => 50.0,
            'width_meters' => 15.0,
            'height_meters' => 25.0,
        ]);

        $this->assertEquals('STP-ACCEPTANCE-01', $stope->stope_code);
        $this->assertEquals('DEVELOPMENT', $stope->status);
        $this->assertGreaterThan(0.0, $stope->volume_m3);

        $sim = $ventAction->execute([
            'simulation_code' => 'SIM-VENT-ACCEPTANCE-01',
            'fan_rpm_percent' => 100.0,
            'headcount_underground' => 20,
            'diesel_fleet_kw' => 300.0,
            'branches' => [
                [
                    'id' => 'BR-IN-ACC',
                    'from_node' => 'SURFACE',
                    'to_node' => 'J1',
                    'resistance_r' => 0.040,
                    'initial_flow_q' => 120.0,
                    'fan_pressure_pf' => 1400.0,
                    'is_fan_branch' => true,
                ],
                [
                    'id' => 'BR-WORK-ACC',
                    'from_node' => 'J1',
                    'to_node' => 'J2',
                    'resistance_r' => 0.090,
                    'initial_flow_q' => 60.0,
                ],
                [
                    'id' => 'BR-RET-ACC',
                    'from_node' => 'J2',
                    'to_node' => 'SURFACE',
                    'resistance_r' => 0.045,
                    'initial_flow_q' => 120.0,
                ],
            ],
            'loops' => [
                [
                    'loop_id' => 'LP-ACC-1',
                    'branch_ids' => ['BR-IN-ACC', 'BR-WORK-ACC', 'BR-RET-ACC'],
                    'directions' => [1, 1, -1],
                ],
            ],
        ]);

        $this->assertEquals('VALID', $sim->status);
        $this->assertTrue($sim->is_converged);
        $this->assertGreaterThan(0.0, $sim->intake_airflow_m3_s);
    }

    /**
     * Skenario 2: Pencegahan Penembakan Peledakan Akibat Pelanggaran Geofence Personel.
     */
    public function test_blasting_safety_perimeter_breach_and_interlock_abort(): void
    {
        $run = new MiningBlastClearanceRun;
        $run->fill([
            'run_code' => 'BLAST-BREACH-ACC-01',
            'pattern_id' => 'PAT-BREACH-01',
            'pit_name' => 'Pit North Test',
            'center_latitude' => -3.421500,
            'center_longitude' => 114.598200,
            'safety_radius_meters' => 500.0,
            'planned_holes_count' => 24,
            'total_explosives_kg' => 1200.0,
            'status' => BlastClearanceStatus::DRAFT->value,
            'all_personnel_evacuated' => false,
        ]);
        $run->save();

        $site = MiningSite::query()->firstOrCreate(
            ['site_code' => 'STE-TEST-NORTH'],
            [
                'name' => 'Site Pit North',
                'concession_type' => 'IUP_OP',
                'commodity' => 'NICKEL',
                'location' => 'Halmahera Timur',
                'area_hectares' => 500.0,
                'status' => 'ACTIVE',
            ]
        );

        // Buat izin kerja aktif tepat di dalam radius peledakan (pelanggaran geofence)
        MiningWorkPermit::query()->create([
            'site_id' => $site->id,
            'permit_number' => 'WP-TEST-BREACH-99',
            'permit_type' => 'HOT_WORK',
            'approval_status' => 'APPROVED',
            'allowed_latitude' => -3.421600, // Berjarak ~15 meter dari titik ledak
            'allowed_longitude' => 114.598250,
            'allowed_radius_meters' => 200.0,
            'worker_party_id' => 'WORKER-BREACH-01',
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addHour(),
        ]);

        $verifyAction = app(VerifyBlastPerimeterClearanceAction::class);
        $verifyResult = $verifyAction->execute(['blast_clearance_run_id' => $run->id]);

        $this->assertFalse($verifyResult['is_perimeter_safe']);
        $this->assertGreaterThan(0, $verifyResult['detected_breaches_count']);
        $this->assertNotNull($verifyResult['rejection_reason']);

        // Pastikan peniupan sirine ditolak karena perimeter belum steril
        $sirenAction = app(TriggerBlastWarningSirenAction::class);
        $this->expectException(\InvalidArgumentException::class);
        $sirenAction->execute([
            'blast_clearance_run_id' => $run->id,
            'siren_stage' => 'STAGE_3_FINAL_1MIN',
            'blaster_in_charge_kjl' => 'KJL-TEST-01',
        ]);
    }

    /**
     * Skenario 3: Siklus Peledakan Terkendali Penuh, Detonasi & Evaluasi Fragmentasi KCO (M8).
     */
    public function test_controlled_detonation_and_kco_fragmentation_evaluation(): void
    {
        $run = new MiningBlastClearanceRun;
        $run->fill([
            'run_code' => 'BLAST-DETONATE-ACC-01',
            'pattern_id' => 'PAT-CLEARED-01',
            'pit_name' => 'Pit Alpha Safe',
            'center_latitude' => -3.550000,
            'center_longitude' => 114.750000,
            'safety_radius_meters' => 500.0,
            'planned_holes_count' => 30,
            'total_explosives_kg' => 1500.0,
            'status' => BlastClearanceStatus::DRAFT->value,
            'all_personnel_evacuated' => false,
        ]);
        $run->save();

        // 1. Verifikasi clearance (0 personel di koordinat ini)
        $verifyAction = app(VerifyBlastPerimeterClearanceAction::class);
        $verifyResult = $verifyAction->execute(['blast_clearance_run_id' => $run->id]);
        $this->assertTrue($verifyResult['is_perimeter_safe']);
        $run->refresh();

        // 2. Bunyikan sirine
        $sirenAction = app(TriggerBlastWarningSirenAction::class);
        $sirenResult = $sirenAction->execute([
            'blast_clearance_run_id' => $run->id,
            'siren_stage' => 'STAGE_3_FINAL_1MIN',
            'blaster_in_charge_kjl' => 'KJL-CERTIFIED-991',
        ]);
        $this->assertEquals(BlastClearanceStatus::SIREN_ACTIVE->value, $sirenResult['status']);

        // 3. Eksekusi peledakan via Saga & Ledger Posting
        $detonateAction = app(ExecuteControlledDetonationAction::class);
        $detonateResult = $detonateAction->execute([
            'blast_clearance_run_id' => $run->id,
            'initiated_by_user_id' => 'KTT-MASTER-BLASTER',
            'detonator_box_serial' => 'EXPLODER-DIGITAL-ACC',
            'total_explosive_cost_idr' => 25000000.0,
        ]);
        $this->assertTrue($detonateResult['is_success']);
        $this->assertNotNull($detonateResult['ledger_journal_uuid']);

        // 4. Evaluasi fragmentasi batuan KCO
        $evalAction = app(EvaluateMuckpileKcoFragmentationAction::class);
        $eval = $evalAction->execute([
            'blast_clearance_run_id' => $run->id,
            'eval_code' => 'EVAL-KCO-ACC-01',
            'rock_factor_a' => 8.0,
            'burden_meters' => 3.5,
            'spacing_meters' => 4.2,
            'hole_diameter_mm' => 165.0,
            'bench_height_meters' => 12.0,
            'charge_length_meters' => 8.5,
            'explosive_rws' => 105.0,
            'explosive_density' => 1.15,
        ]);

        $this->assertGreaterThan(0.0, $eval->mean_fragment_size_d50_mm);
        $this->assertNotNull($eval->hmac_signature);
    }

    /**
     * Skenario 4: Telemetri Radar Lereng Kritis, Evakuasi KTT & Resolusi Pasca-Remediasi.
     */
    public function test_geotech_slope_radar_telemetry_and_emergency_evacuation(): void
    {
        $ingestAction = app(IngestSlopeStabilityTelemetryAction::class);
        $evacuateAction = app(TriggerGeotechEvacuationAlarmAction::class);
        $resolveAction = app(ResolveGeotechIncidentAction::class);

        // 1. Ingest telemetri deformasi tinggi (18.5 mm/h)
        $incident = $ingestAction->execute([
            'location_sector' => 'Pit South Wall Bench 10',
            'radar_unit_id' => 'RADAR-ACC-01',
            'displacement_rate_mm_hour' => 18.5,
            'cumulative_displacement_mm' => 95.0,
            'factor_of_safety_fos' => 1.01,
            'latitude' => -3.450000,
            'longitude' => 114.650000,
        ]);

        $this->assertEquals('CRITICAL', $incident->severity_level);
        $this->assertEquals(GeotechIncidentStatus::AREA_EVACUATED->value, $incident->status);
        $this->assertTrue($incident->is_evacuation_ordered);

        // 2. KTT konfirmasi perintah evakuasi
        $evacResult = $evacuateAction->execute([
            'geotech_incident_id' => $incident->id,
            'ordered_by_ktt_name' => 'KTT Operation Head',
            'evacuation_reason' => 'Percepatan pergerakan dinding lereng melebihi 15 mm/h',
            'safe_muster_point_sector' => 'Muster Point Bravo South Gate',
        ]);
        $this->assertTrue($evacResult['is_evacuation_ordered']);

        // 3. Resolusi pasca-remediasi (laju kembali normal 0.4 mm/h dan FoS 1.45)
        $resolveResult = $resolveAction->execute([
            'geotech_incident_id' => $incident->id,
            'closing_engineer_name' => 'Principal Geotechnical Engineer',
            'verified_stable_displacement_rate_mm_h' => 0.40,
            'verified_factor_of_safety' => 1.45,
            'signoff_notes' => 'Pemasangan penahan tanggul buttress berhasil menstabilkan lereng.',
        ]);

        $this->assertEquals(GeotechIncidentStatus::ALL_CLEAR->value, $resolveResult['status']);
    }

    /**
     * Skenario 5: Survei Draf Tongkang 6-Titik, Rekonsiliasi Hidrostatik (M9) & Settlement Freight Buku Besar.
     */
    public function test_marine_barge_six_point_draft_survey_and_freight_settlement_ledger(): void
    {
        $recordAction = app(RecordSixPointDraftSurveyAction::class);
        $reconcileAction = app(ReconcileHydrostaticBargeTonnageAction::class);
        $chainAction = app(AuditBargeChainOfCustodyAction::class);
        $freightAction = app(SettleBargeFreightTariffAction::class);

        // 1. Initial draft survey
        $initial = $recordAction->execute([
            'barge_code' => 'BG-ACCEPTANCE-99',
            'survey_type' => 'INITIAL_LIGHT',
            'surveyor_name' => 'Surv. Ahmad Syarif',
            'draft_forward_port_m' => 0.90,
            'draft_forward_starboard_m' => 0.92,
            'draft_midship_port_m' => 0.95,
            'draft_midship_starboard_m' => 0.96,
            'draft_aft_port_m' => 1.30,
            'draft_aft_starboard_m' => 1.32,
            'water_density_measured_kg_l' => 1.025,
        ]);
        $this->assertEquals(BargeShipmentStatus::BERTHED->value, $initial->status);

        // 2. Final loaded draft survey
        $final = $recordAction->execute([
            'barge_code' => 'BG-ACCEPTANCE-99',
            'survey_type' => 'FINAL_LOADED',
            'surveyor_name' => 'Surv. Ahmad Syarif',
            'draft_forward_port_m' => 4.80,
            'draft_forward_starboard_m' => 4.82,
            'draft_midship_port_m' => 4.88,
            'draft_midship_starboard_m' => 4.90,
            'draft_aft_port_m' => 5.25,
            'draft_aft_starboard_m' => 5.28,
            'water_density_measured_kg_l' => 1.025,
        ]);
        $this->assertEquals(BargeShipmentStatus::LOADED->value, $final->status);

        // 3. Rekonsiliasi hidrostatik (M9) vs Timbangan Jetty
        $reconcileResult = $reconcileAction->execute([
            'barge_code' => 'BG-ACCEPTANCE-99',
            'initial_survey_id' => $initial->id,
            'final_survey_id' => $final->id,
            'weighbridge_loaded_tonnage_mt' => 5950.0,
            'tolerance_variance_percent' => 2.50,
        ]);
        $this->assertGreaterThan(5000.0, $reconcileResult['net_cargo_tonnage_wmt']);
        $this->assertEquals(BargeShipmentStatus::DRAFT_SURVEYED->value, $reconcileResult['status']);

        // 4. Audit Chain of Custody & SPB
        $chainResult = $chainAction->execute([
            'barge_draft_survey_id' => $final->id,
            'jetty_id' => 'JETTY-SPECIAL-01',
            'syahbandar_spb_number' => 'SPB-PORT-202611-001',
            'certificate_of_analysis_number' => 'COA-SUCOFINDO-9921',
            'tamper_seal_numbers' => ['SEAL-001', 'SEAL-002'],
            'auditor_name' => 'Port Marine Inspector',
            'destination_port' => 'Smelter Jetty Morowali',
        ]);
        $this->assertTrue($chainResult['is_chain_of_custody_intact']);
        $this->assertEquals(BargeShipmentStatus::IN_TRANSIT->value, $chainResult['status']);

        // 5. Freight Tariff Settlement & Ledger Posting
        $freightResult = $freightAction->execute([
            'barge_draft_survey_id' => $final->id,
            'contract_freight_rate_per_ton_idr' => 150000.0,
            'allowed_laytime_hours' => 72.0,
            'actual_laytime_hours' => 60.0, // Hemat 12 jam (Despatch)
            'demurrage_rate_per_day_idr' => 40000000.0,
        ]);
        $this->assertGreaterThan(0.0, $freightResult['net_freight_payable_idr']);
        $this->assertNotNull($freightResult['ledger_journal_uuid']);
        $this->assertEquals(BargeShipmentStatus::SETTLED->value, $freightResult['status']);
    }
}
