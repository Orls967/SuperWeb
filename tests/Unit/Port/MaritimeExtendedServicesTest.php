<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Port\Application\Services\BunkerProcurementAndHedgingService;
use Modules\Port\Application\Services\CanalTransitAndPassageTollService;
use Modules\Port\Application\Services\CrewRotationAndPayrollService;
use Modules\Port\Application\Services\MaritimeCargoCustodyService;
use Modules\Port\Application\Services\MaritimeInsuranceClaimService;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Application\Services\VesselMaintenanceWorkOrderService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Maritime Extended Application Services', function (): void {
    it('evaluates PMS vessel maintenance work orders based on running hours', function (): void {
        $fleetService = app(PortFleetService::class);
        $vessel = $fleetService->registerVessel([
            'imo_number' => '9112233',
            'vessel_name' => 'MV Engine Test',
            'vessel_type' => 'BULK_CARRIER',
            'deadweight_tonnage' => 50000,
            'gross_tonnage' => 30000,
            'net_tonnage' => 18000,
            'length_overall_cm' => 19000,
            'beam_cm' => 3200,
            'design_draft_cm' => 1200,
            'main_engine_power_kw' => 8500,
        ]);

        $maintService = new VesselMaintenanceWorkOrderService;

        // 5500 jam (belum due untuk interval 6000 jam)
        $notDue = $maintService->evaluateComponentMaintenance((int) $vessel->id, 'Turbocharger Rotor', 5500, 6000);
        expect($notDue['is_due'])->toBeFalse();

        // 6200 jam (sudah due)
        $isDue = $maintService->evaluateComponentMaintenance((int) $vessel->id, 'Turbocharger Rotor', 6200, 6000);
        expect($isDue['is_due'])->toBeTrue()
            ->and($isDue['action_taken'])->toContain('overhaul berkala');
    });

    it('calculates draft survey net cargo weight applying keel sag-hog and water density corrections', function (): void {
        $custodyService = new MaritimeCargoCustodyService;

        // Kapal Supramax memuat batubara:
        // Awal (Ballast): Fwd 3.2m, Aft 5.8m, Mid 4.5m -> Quarter mean ≈ 4.5m
        // Akhir (Laden): Fwd 12.1m, Aft 12.7m, Mid 12.4m -> Quarter mean ≈ 12.4m
        $survey = $custodyService->calculateDraftSurvey(
            3.20,
            5.80,
            4.50,
            12.10,
            12.70,
            12.40,
            1.025,
            1.025,
            55.0, // 55 MT/cm
            400.0 // Constant deductible
        );

        expect($survey['mean_draft_initial_m'])->toBeGreaterThan(4.0)
            ->and($survey['mean_draft_final_m'])->toBeGreaterThan(12.0)
            ->and($survey['net_cargo_loaded_mt'])->toBeGreaterThan(40000.0);
    });

    it('evaluates bunker quality certificate against ISO 8217 limits and detects flash point breach', function (): void {
        $bunkerService = new BunkerProcurementAndHedgingService;

        $validLab = [
            'sample_seal_number' => 'SEAL-VALID-01',
            'fuel_grade' => 'VLSFO',
            'density_15c_kg_m3' => 991.0,
            'kinematic_viscosity_50c_cst' => 380.0,
            'flash_point_celsius' => 68.0, // Aman (> 60C)
            'sulphur_mass_pct' => 0.48, // Aman (< 0.50%)
            'water_volume_pct' => 0.25, // Aman (< 0.50%)
            'al_plus_si_mg_kg' => 35.0, // Aman (< 60)
            'total_sediment_potential_mass_pct' => 0.05,
        ];

        $resValid = $bunkerService->evaluateBunkerQualityCertificate($validLab);
        expect($resValid['is_on_spec'])->toBeTrue()
            ->and($resValid['violations'])->toBeEmpty();

        $corruptLab = [
            'sample_seal_number' => 'SEAL-DANGEROUS-01',
            'fuel_grade' => 'VLSFO',
            'density_15c_kg_m3' => 998.0,
            'kinematic_viscosity_50c_cst' => 450.0,
            'flash_point_celsius' => 54.0, // BAHAYA (< 60C)
            'sulphur_mass_pct' => 0.58, // OFF-SPEC (> 0.50%)
            'water_volume_pct' => 0.80,
            'al_plus_si_mg_kg' => 85.0,
            'total_sediment_potential_mass_pct' => 0.15,
        ];

        $resCorrupt = $bunkerService->evaluateBunkerQualityCertificate($corruptLab);
        expect($resCorrupt['is_on_spec'])->toBeFalse()
            ->and($resCorrupt['violations'])->toHaveCount(4);
    });

    it('computes Suez Canal transit toll with SCNT tiers and Malacca Strait passage dues', function (): void {
        $canalService = new CanalTransitAndPassageTollService;

        // 35,000 SCNT Bulk Carrier
        $suez = $canalService->estimateSuezCanalToll(35000.0, 'BULK_CARRIER', true, true);
        expect($suez['canal'])->toBe('SUEZ_CANAL')
            ->and($suez['total_transit_cost_usd'])->toBeGreaterThan(300000.0);

        // Selat Malaka TSS
        $malacca = $canalService->estimateMalaccaStraitPassageDues(32000.0, 12.5);
        expect($malacca['strait'])->toBe('STRAIT_OF_MALACCA_TSS')
            ->and($malacca['deep_water_route_mandatory'])->toBeFalse()
            ->and($malacca['total_passage_cost_usd'])->toBeGreaterThan(2500.0);
    });

    it('binds war risk insurance cover and computes deductible for P&I claim', function (): void {
        $fleetService = app(PortFleetService::class);
        $vessel = $fleetService->registerVessel([
            'imo_number' => '9443322',
            'vessel_name' => 'MV Red Sea Pioneer',
            'vessel_type' => 'BULK_CARRIER',
            'deadweight_tonnage' => 50000,
            'gross_tonnage' => 30000,
            'net_tonnage' => 18000,
            'length_overall_cm' => 19000,
            'beam_cm' => 3200,
            'design_draft_cm' => 1200,
            'main_engine_power_kw' => 8500,
        ]);

        $insService = new MaritimeInsuranceClaimService;

        $claim = $insService->fileProtectionAndIndemnityClaim(
            (int) $vessel->id,
            'CARGO_DAMAGE',
            85000.0,
            15000.0 // deductible
        );

        expect($claim['club_liability_estimate_usd'])->toBe(70000.0); // 85k - 15k
    });

    it('audits MLC 2006 seafarer rest hours compliance and flags critical fatigue deficiency', function (): void {
        $crewingService = new CrewRotationAndPayrollService;

        // Log 7 hari patuh: 11 jam istirahat per hari (77 jam total), periode terpanjang 7 jam
        $compliantLog = [];
        for ($i = 1; $i <= 7; $i++) {
            $compliantLog[] = [
                'date' => "2026-10-0{$i}",
                'rest_hours_24h' => 11.0,
                'longest_period_hours' => 7.0,
            ];
        }

        $resCompliant = $crewingService->auditMlcRestHoursCompliance($compliantLog);
        expect($resCompliant['is_compliant'])->toBeTrue()
            ->and($resCompliant['fatigue_risk_level'])->toBe('LOW');

        // Log 7 hari kelelahan ekstrem: hanya 6 jam per hari (42 jam total)
        $fatigueLog = [];
        for ($i = 1; $i <= 7; $i++) {
            $fatigueLog[] = [
                'date' => "2026-10-0{$i}",
                'rest_hours_24h' => 6.0,
                'longest_period_hours' => 4.0,
            ];
        }

        $resFatigue = $crewingService->auditMlcRestHoursCompliance($fatigueLog);
        expect($resFatigue['is_compliant'])->toBeFalse()
            ->and($resFatigue['fatigue_risk_level'])->toBe('CRITICAL')
            ->and($resFatigue['deficiencies'])->not->toBeEmpty();
    });
});
