<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Port\Application\Actions\AllocateBerthAndPilotageAction;
use Modules\Port\Application\Actions\AuditVesselStatutoryComplianceAction;
use Modules\Port\Application\Actions\DeclareGeneralAverageSacrificeAction;
use Modules\Port\Application\Actions\ExecuteCharterFixtureAgreementAction;
use Modules\Port\Application\Actions\IssueDemurrageDespatchInvoiceAction;
use Modules\Port\Application\Actions\OptimizeVoyageSpeedAndBunkerAction;
use Modules\Port\Application\Actions\PerformDraftSurveyInspectionAction;
use Modules\Port\Application\Actions\RecordNoonTelemetryAction;
use Modules\Port\Application\Actions\ScheduleVesselVoyageAction;
use Modules\Port\Application\Actions\SettlePortFinalDisbursementAction;
use Modules\Port\Application\Services\MarineServicesDisbursementService;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Domain\Models\FleetPortCall;
use Modules\Port\Domain\Models\FleetPortDisbursement;
use Modules\Port\Domain\Models\FleetVessel;
use Modules\Port\Domain\Models\FleetVoyage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Maritime Application Actions Suite', function (): void {
    beforeEach(function (): void {
        $fleetService = app(PortFleetService::class);
        $this->vessel = $fleetService->registerVessel([
            'imo_number' => '9778899',
            'vessel_name' => 'MV Action Test',
            'vessel_type' => 'BULK_CARRIER',
            'deadweight_tonnage' => 55000,
            'gross_tonnage' => 32000,
            'net_tonnage' => 19000,
            'length_overall_cm' => 19000,
            'beam_cm' => 3200,
            'design_draft_cm' => 1250,
            'main_engine_power_kw' => 8800,
            'design_speed_knots' => 14.5,
        ]);

        $fleetService->configureBunkerTank((int) $this->vessel->id, 'TK-1', 'Main Tank', 'VLSFO', 800.0, 600.0);
        $fleetService->recordCertificate((int) $this->vessel->id, 'SAFETY_CONSTRUCTION', 'SC-ACT-01', 'BKI', CarbonImmutable::now()->subMonths(1), CarbonImmutable::now()->addYears(3));
        $fleetService->recordSafeManning((int) $this->vessel->id, 'SMD-ACT-01', 4, 4, 6, 4, 2, 20);
    });

    it('schedules a valid voyage via ScheduleVesselVoyageAction', function (): void {
        $action = app(ScheduleVesselVoyageAction::class);
        $now = CarbonImmutable::now();

        $voyage = $action->execute(
            (int) $this->vessel->id,
            'VOY-ACT-01',
            '01/2026',
            'IDJKT',
            'IDSUB',
            'Tanjung Priok',
            'Tanjung Perak',
            $now,
            $now->addDays(3),
            450.0,
            13.5
        );

        expect($voyage->voyage_code)->toBe('VOY-ACT-01')
            ->and($voyage->planned_distance_nm)->toBe(450.0);
    });

    it('records noon telemetry via RecordNoonTelemetryAction with coordinates validation', function (): void {
        $action = app(RecordNoonTelemetryAction::class);
        $voyage = FleetVoyage::create([
            'voyage_code' => 'VOY-NOON-01',
            'vessel_id' => $this->vessel->id,
            'voyage_number' => '01/2026',
            'origin_port' => 'IDJKT',
            'destination_port' => 'IDSUB',
            'load_port' => 'Tanjung Priok',
            'discharge_port' => 'Tanjung Perak',
            'planned_start_at' => CarbonImmutable::now(),
            'planned_end_at' => CarbonImmutable::now()->addDays(2),
            'status' => 'LADEN_PASSAGE',
        ]);

        $report = $action->execute(
            $voyage->id,
            CarbonImmutable::now(),
            -6.1020,
            106.8520,
            90.0,
            13.2,
            305.0,
            3.0,
            'SE',
            2.0,
            84.0,
            4.5,
            24.0,
            1.8,
            'Pelayaran normal'
        );

        expect($report->latitude)->toBe(-6.1020)
            ->and($report->speed_gps_knots)->toBe(13.2);
    });

    it('executes charter fixture agreement via ExecuteCharterFixtureAgreementAction', function (): void {
        $action = app(ExecuteCharterFixtureAgreementAction::class);
        $now = CarbonImmutable::now();

        $party = $action->execute(
            (int) $this->vessel->id,
            'CP-FIXTURE-ACT-01',
            'Cargill Ocean Transportation',
            'VOYAGE_CHARTER',
            'GENCON_1994',
            $now,
            $now->addDays(4),
            15.50
        );

        expect($party->fixture_number)->toBe('CP-FIXTURE-ACT-01')
            ->and($party->status)->toBe('FIXTURE_AGREED');
    });

    it('allocates berth, pilotage, and tugboats via AllocateBerthAndPilotageAction', function (): void {
        $action = app(AllocateBerthAndPilotageAction::class);
        $marineService = app(MarineServicesDisbursementService::class);
        $now = CarbonImmutable::now();

        $voyage = FleetVoyage::create([
            'voyage_code' => 'VOY-BERTH-01',
            'vessel_id' => $this->vessel->id,
            'voyage_number' => '01/2026',
            'origin_port' => 'IDJKT',
            'destination_port' => 'IDSUB',
            'load_port' => 'Tanjung Priok',
            'discharge_port' => 'Tanjung Perak',
            'planned_start_at' => $now,
            'planned_end_at' => $now->addDays(2),
        ]);

        $portCall = $marineService->schedulePortCall(
            $voyage->id,
            'IDJKT',
            'Tanjung Priok',
            'LOADING',
            $now
        );

        $res = $action->execute($portCall->id, $now, 48, true);

        expect($res['berth_booking'])->not->toBeNull()
            ->and($res['pilotage_request_id'])->not->toBeNull()
            ->and($res['tug_count'])->toBeGreaterThanOrEqual(1);
    });

    it('issues demurrage invoice via IssueDemurrageDespatchInvoiceAction', function (): void {
        $action = app(IssueDemurrageDespatchInvoiceAction::class);
        $fixtureAction = app(ExecuteCharterFixtureAgreementAction::class);
        $now = CarbonImmutable::now();

        $charter = $fixtureAction->execute(
            (int) $this->vessel->id,
            'CP-DEMURRAGE-ACT-01',
            'Louis Dreyfus Company',
            'VOYAGE_CHARTER',
            'GENCON_1994',
            $now->subDays(5),
            $now,
            16.00,
            48.0, // 48 jam allowed
            'WWD_SHINC',
            20000.0
        );

        // Operasi memakan 80 jam (demurrage 32 jam)
        $res = $action->execute(
            (int) $charter->id,
            null,
            'SOF-ACT-01',
            $now->subDays(4),
            $now->subDays(4)->addHours(6),
            $now->subDays(4)->addHours(86)
        );

        expect($res['result_type'])->toBe('DEMURRAGE')
            ->and($res['demurrage_hours'])->toBe(32.0)
            ->and($res['invoice_id'])->not->toBeNull();
    });

    it('declares general average sacrifice and writes double-entry ledger via DeclareGeneralAverageSacrificeAction', function (): void {
        $action = app(DeclareGeneralAverageSacrificeAction::class);
        $now = CarbonImmutable::now();

        $voyage = FleetVoyage::create([
            'voyage_code' => 'VOY-GA-ACT-01',
            'vessel_id' => $this->vessel->id,
            'voyage_number' => '01/2026',
            'origin_port' => 'IDJKT',
            'destination_port' => 'SGSIN',
            'load_port' => 'Tanjung Priok',
            'discharge_port' => 'Jurong Port Singapore',
            'planned_start_at' => $now,
            'planned_end_at' => $now->addDays(2),
        ]);

        $ga = $action->execute(
            (int) $voyage->id,
            'GA-ACT-2026-01',
            'Insiden kebakaran di palka 2, air pemadam disemprotkan demi keselamatan kapal',
            'Average Adjusters International',
            15000000.0,
            10000000.0,
            500000.0,
            [
                ['interest' => 'CARGO', 'description' => 'Kerusakan kargo akibat air pemadam', 'amount_usd' => 250000.0, 'category' => 'SACRIFICE'],
                ['interest' => 'SHIP', 'description' => 'Bantuan kapal pemadam pelabuhan', 'amount_usd' => 80000.0, 'category' => 'EXPENDITURE'],
            ]
        );

        expect($ga->case_number)->toBe('GA-ACT-2026-01')
            ->and($ga->general_average_loss_usd)->toBe(330000.0);
    });

    it('verifies draft survey tolerance via PerformDraftSurveyInspectionAction', function (): void {
        $action = app(PerformDraftSurveyInspectionAction::class);
        $marineService = app(MarineServicesDisbursementService::class);
        $now = CarbonImmutable::now();

        $voyage = FleetVoyage::create([
            'voyage_code' => 'VOY-SURVEY-01',
            'vessel_id' => $this->vessel->id,
            'voyage_number' => '01/2026',
            'origin_port' => 'IDJKT',
            'destination_port' => 'IDSUB',
            'load_port' => 'Tanjung Priok',
            'discharge_port' => 'Tanjung Perak',
            'planned_start_at' => $now,
            'planned_end_at' => $now->addDays(2),
        ]);

        $portCall = $marineService->schedulePortCall($voyage->id, 'IDJKT', 'Tanjung Priok', 'LOADING', $now);

        // Draft Survey menghasilkan 40,800 MT untuk deklarasi B/L 40,800 MT (selisih 0.0% -> within tolerance)
        $res = $action->execute(
            $portCall->id,
            4.0,
            6.0,
            5.0, // Init draft mean 5.0m
            12.0,
            13.0,
            12.5, // Fin draft mean 12.5m
            40800.0,
            55.0
        );

        expect($res['is_within_tolerance'])->toBeTrue()
            ->and($res['letter_of_protest_required'])->toBeFalse();
    });

    it('settles FDA and records variance adjustments in ledger via SettlePortFinalDisbursementAction', function (): void {
        $action = app(SettlePortFinalDisbursementAction::class);
        $marineService = app(MarineServicesDisbursementService::class);
        $now = CarbonImmutable::now();

        $voyage = FleetVoyage::create([
            'voyage_code' => 'VOY-DISB-01',
            'vessel_id' => $this->vessel->id,
            'voyage_number' => '01/2026',
            'origin_port' => 'IDJKT',
            'destination_port' => 'IDSUB',
            'load_port' => 'Tanjung Priok',
            'discharge_port' => 'Tanjung Perak',
            'planned_start_at' => $now,
            'planned_end_at' => $now->addDays(2),
        ]);

        $portCall = $marineService->schedulePortCall($voyage->id, 'IDJKT', 'Tanjung Priok', 'LOADING', $now);
        $marineService->generateProformaDisbursement($portCall->id, 'PDA-01', 5000.0, 1500.0, 2000.0, 3000.0);

        // Biaya aktual bertambah karena extra pilotage hours
        $fda = $action->execute($portCall->id, 'FDA-01', 5000.0, 2200.0, 2000.0, 3000.0);

        expect($fda->disbursement_type)->toBe('FDA')
            ->and($fda->variance_with_pda_usd)->toBe(700.0);
    });

    it('audits expiring certificates via AuditVesselStatutoryComplianceAction', function (): void {
        $action = app(AuditVesselStatutoryComplianceAction::class);
        $now = CarbonImmutable::now();

        $res = $action->execute((int) $this->vessel->id, $now);

        expect($res['is_seaworthy'])->toBeTrue()
            ->and($res['statutory_valid'])->toBeTrue();
    });

    it('optimizes voyage speed and compares full vs eco profiles via OptimizeVoyageSpeedAndBunkerAction', function (): void {
        $action = app(OptimizeVoyageSpeedAndBunkerAction::class);
        $now = CarbonImmutable::now();

        $voyage = FleetVoyage::create([
            'voyage_code' => 'VOY-SPEED-01',
            'vessel_id' => $this->vessel->id,
            'voyage_number' => '01/2026',
            'origin_port' => 'IDJKT',
            'destination_port' => 'IDSUB',
            'load_port' => 'Tanjung Priok',
            'discharge_port' => 'Tanjung Perak',
            'planned_start_at' => $now,
            'planned_end_at' => $now->addDays(2),
            'planned_distance_nm' => 1200.0,
        ]);

        $res = $action->execute($voyage->id, 16.0, 50000.0);

        expect($res['profiles_analysis'])->toHaveKeys(['FULL_SPEED', 'ECO_SPEED', 'SLOW_STEAMING'])
            ->and($res['optimal_profile'])->not->toBeEmpty();
    });
});
