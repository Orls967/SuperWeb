<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Port\Application\Sagas\VoyageCharterExecutionSaga;
use Modules\Port\Application\Services\CommercialCharterService;
use Modules\Port\Application\Services\MarineServicesDisbursementService;
use Modules\Port\Application\Services\PortFleetLedgerService;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Application\Services\VoyageExecutionService;
use Modules\Port\Domain\Models\FleetBerthBooking;
use Modules\Port\Domain\Models\FleetCharterParty;
use Modules\Port\Domain\Models\FleetLedgerEntry;
use Modules\Port\Domain\Models\FleetVoyage;
use Modules\Port\Domain\StateMachines\CharterPartyStateMachine;
use Modules\Port\Domain\StateMachines\VoyageStateMachine;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('VoyageCharterExecutionSaga with Compensation', function (): void {
    it('successfully executes complete saga when vessel is seaworthy and berth is available', function (): void {
        $fleetService = app(PortFleetService::class);
        $saga = app(VoyageCharterExecutionSaga::class);
        $now = CarbonImmutable::now();

        // 1. Daftarkan kapal seaworthy
        $vessel = $fleetService->registerVessel([
            'imo_number' => '9988776',
            'vessel_name' => 'MV Test Navigator',
            'vessel_type' => 'BULK_CARRIER',
            'deadweight_tonnage' => 50000,
            'gross_tonnage' => 30000,
            'net_tonnage' => 18000,
            'length_overall_cm' => 19000,
            'beam_cm' => 3200,
            'design_draft_cm' => 1200,
            'main_engine_power_kw' => 8500,
        ]);

        $fleetService->configureBunkerTank((int) $vessel->id, 'TK-1', 'Main Tank', 'VLSFO', 800.0, 500.0);
        $fleetService->recordCertificate((int) $vessel->id, 'SAFETY_CONSTRUCTION', 'SC-TEST-01', 'BKI', $now->subMonths(1), $now->addYears(3));
        $fleetService->recordSafeManning((int) $vessel->id, 'SMD-TEST-01', 4, 4, 6, 4, 2, 20);

        // 2. Jalankan saga
        $res = $saga->execute([
            'vessel_id' => (int) $vessel->id,
            'fixture_number' => 'CP-SAGA-SUCCESS-01',
            'charterer_name' => 'Glencore Agriculture',
            'charter_type' => 'VOYAGE_CHARTER',
            'freight_or_hire_rate' => 16.50,
            'voyage_code' => 'VOY-SAGA-01',
            'voyage_number' => '01/2026',
            'origin_port' => 'IDJKT',
            'destination_port' => 'IDSUB',
            'load_port' => 'Tanjung Priok',
            'discharge_port' => 'Tanjung Perak',
            'planned_start_at' => $now->addDays(2),
            'planned_end_at' => $now->addDays(6),
            'planned_distance_nm' => 450.0,
            'estimated_pda_usd' => 15000.0,
        ]);

        expect($res['success'])->toBeTrue()
            ->and($res['compensation_applied'])->toBeFalse()
            ->and($res['charter_party_id'])->not->toBeNull()
            ->and($res['voyage_id'])->not->toBeNull()
            ->and($res['berth_booking_id'])->not->toBeNull()
            ->and($res['ledger_status'])->toBe('COMMITTED');

        $charter = FleetCharterParty::find($res['charter_party_id']);
        expect($charter->status)->toBe(CharterPartyStateMachine::STATUS_FIXTURE_AGREED);

        $voyage = FleetVoyage::find($res['voyage_id']);
        expect($voyage->status)->toBe(VoyageStateMachine::STATUS_PLANNED);

        $booking = FleetBerthBooking::find($res['berth_booking_id']);
        expect($booking->booking_status)->toBe('CONFIRMED');
    });

    it('triggers compensation rollback when vessel fails seaworthiness statutory check', function (): void {
        $fleetService = app(PortFleetService::class);
        $saga = app(VoyageCharterExecutionSaga::class);
        $now = CarbonImmutable::now();

        // Kapal tanpa sertifikat statutoria (unseaworthy)
        $vessel = $fleetService->registerVessel([
            'imo_number' => '9911223',
            'vessel_name' => 'MV Defective Ship',
            'vessel_type' => 'BULK_CARRIER',
            'deadweight_tonnage' => 35000,
            'gross_tonnage' => 22000,
            'net_tonnage' => 12000,
            'length_overall_cm' => 16000,
            'beam_cm' => 2800,
            'design_draft_cm' => 1050,
            'main_engine_power_kw' => 6000,
        ]);

        $res = $saga->execute([
            'vessel_id' => (int) $vessel->id,
            'fixture_number' => 'CP-SAGA-FAIL-01',
            'charterer_name' => 'Bunge Global',
            'charter_type' => 'VOYAGE_CHARTER',
            'freight_or_hire_rate' => 12.00,
            'voyage_code' => 'VOY-SAGA-FAIL-01',
            'voyage_number' => '02/2026',
            'origin_port' => 'IDJKT',
            'destination_port' => 'IDSUB',
            'load_port' => 'Tanjung Priok',
            'discharge_port' => 'Tanjung Perak',
            'planned_start_at' => $now->addDays(1),
            'planned_end_at' => $now->addDays(5),
            'planned_distance_nm' => 450.0,
        ]);

        expect($res['success'])->toBeFalse()
            ->and($res['compensation_applied'])->toBeTrue()
            ->and($res['error_message'])->toContain('kelaiklautan');
    });

    it('reverts berth booking and creates reversal ledger entries when downstream error occurs', function (): void {
        $fleetService = app(PortFleetService::class);
        $saga = app(VoyageCharterExecutionSaga::class);
        $now = CarbonImmutable::now();

        // Kapal dengan draft ekstrem (25 meter) yang tidak muat di dermaga manapun (max draft 14.5m)
        $vessel = $fleetService->registerVessel([
            'imo_number' => '9944556',
            'vessel_name' => 'VLOC Giant Carrier',
            'vessel_type' => 'BULK_CARRIER',
            'deadweight_tonnage' => 300000,
            'gross_tonnage' => 160000,
            'net_tonnage' => 95000,
            'length_overall_cm' => 34000,
            'beam_cm' => 6000,
            'design_draft_cm' => 2500, // 25 meter draft
            'main_engine_power_kw' => 25000,
        ]);

        $fleetService->configureBunkerTank((int) $vessel->id, 'TK-1', 'Main Tank', 'VLSFO', 3000.0, 1500.0);
        $fleetService->recordCertificate((int) $vessel->id, 'SAFETY_CONSTRUCTION', 'SC-GIANT-01', 'BKI', $now->subMonths(1), $now->addYears(3));
        $fleetService->recordSafeManning((int) $vessel->id, 'SMD-GIANT-01', 5, 5, 8, 6, 3, 27);

        $res = $saga->execute([
            'vessel_id' => (int) $vessel->id,
            'fixture_number' => 'CP-SAGA-DRAFT-01',
            'charterer_name' => 'Vale Mining',
            'charter_type' => 'VOYAGE_CHARTER',
            'freight_or_hire_rate' => 22.00,
            'voyage_code' => 'VOY-SAGA-DRAFT-01',
            'voyage_number' => '03/2026',
            'origin_port' => 'IDJKT',
            'destination_port' => 'IDSUB',
            'load_port' => 'Tanjung Priok',
            'discharge_port' => 'Tanjung Perak',
            'planned_start_at' => $now->addDays(1),
            'planned_end_at' => $now->addDays(5),
            'planned_distance_nm' => 450.0,
        ]);

        expect($res['success'])->toBeFalse()
            ->and($res['compensation_applied'])->toBeTrue()
            ->and($res['error_message'])->toContain('jendela dermaga');
    });
});
