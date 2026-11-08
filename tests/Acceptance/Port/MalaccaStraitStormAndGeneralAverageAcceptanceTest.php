<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Port\Application\Services\CommercialCharterService;
use Modules\Port\Application\Services\GeneralAverageAdjustmentService;
use Modules\Port\Application\Services\MarineServicesDisbursementService;
use Modules\Port\Application\Services\MaritimeEsgCiiService;
use Modules\Port\Application\Services\PortFleetLedgerService;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Application\Services\VoyageExecutionService;
use Modules\Port\Domain\Models\FleetGeneralAverageCase;
use Modules\Port\Domain\Models\FleetNoonReport;
use Modules\Port\Domain\Models\FleetVoyage;
use Modules\Port\Domain\StateMachines\CharterPartyStateMachine;
use Modules\Port\Domain\StateMachines\VoyageStateMachine;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Simulasi T1: Insiden Badai Selat Malaka, Deviasi, Sengketa Laytime & General Average', function (): void {
    it('executes full end-to-end maritime disaster mitigation and financial settlement', function (): void {
        $fleetService = app(PortFleetService::class);
        $voyageService = app(VoyageExecutionService::class);
        $charterService = app(CommercialCharterService::class);
        $marineService = app(MarineServicesDisbursementService::class);
        $gaService = app(GeneralAverageAdjustmentService::class);
        $esgService = app(MaritimeEsgCiiService::class);
        $ledgerService = app(PortFleetLedgerService::class);
        $now = CarbonImmutable::parse('2026-10-15 06:00:00');

        // 1. Registrasi Kapal Supramax
        $vessel = $fleetService->registerVessel([
            'imo_number' => '9554433',
            'vessel_name' => 'MV Malacca Pioneer',
            'vessel_type' => 'BULK_CARRIER',
            'deadweight_tonnage' => 56000,
            'gross_tonnage' => 32000,
            'net_tonnage' => 18500,
            'length_overall_cm' => 18990,
            'beam_cm' => 3226,
            'design_draft_cm' => 1280,
            'main_engine_power_kw' => 8400,
            'design_speed_knots' => 14.0,
            'sfoc_design_grams_kwh' => 174.0,
        ]);

        $fleetService->configureBunkerTank((int) $vessel->id, 'TK-1', 'Heavy Fuel Tank', 'VLSFO', 900.0, 750.0);
        $fleetService->recordCertificate((int) $vessel->id, 'SAFETY_CONSTRUCTION', 'SC-MALACCA-01', 'BKI', $now->subMonths(2), $now->addYears(3));
        $fleetService->recordSafeManning((int) $vessel->id, 'SMD-MALACCA-01', 4, 4, 6, 4, 2, 20);

        // 2. Fixture Charter Party: Voyage Charter dari Dumai ke Singapura
        $charter = $charterService->createCharterParty([
            'fixture_number' => 'CP-MALACCA-2026-01',
            'vessel_id' => (int) $vessel->id,
            'charterer_name' => 'Wilmar Trading International',
            'charter_type' => 'VOYAGE_CHARTER',
            'freight_or_hire_rate' => 18.00, // USD/MT
            'laytime_allowed_hours' => 48.0,
            'laytime_terms' => 'WWD_SHEX', // Weather Working Days, Sundays/Holidays Excluded
            'demurrage_rate_daily' => 18000.0,
            'despatch_rate_daily' => 9000.0,
            'laycan_commencement' => $now,
            'laycan_cancellation' => $now->addDays(5),
        ]);

        $charterService->transitionStatus((int) $charter->id, CharterPartyStateMachine::STATUS_FIXTURE_AGREED);

        // 3. Pelayaran & Passage Plan
        $voyage = $voyageService->planVoyage([
            'voyage_code' => 'VOY-MALACCA-STORM-01',
            'vessel_id' => (int) $vessel->id,
            'charter_party_id' => (int) $charter->id,
            'voyage_number' => 'VOY-10/2026',
            'origin_port' => 'IDDUM', // Dumai Riau
            'destination_port' => 'SGSIN', // Singapore Jurong
            'load_port' => 'Pelabuhan Dumai',
            'discharge_port' => 'Jurong Port Singapore',
            'planned_start_at' => $now,
            'planned_end_at' => $now->addDays(3),
            'planned_distance_nm' => 280.0,
        ]);

        $voyageService->transitionVoyageStatus((int) $voyage->id, VoyageStateMachine::STATUS_BALLAST_PASSAGE);
        $voyageService->transitionVoyageStatus((int) $voyage->id, VoyageStateMachine::STATUS_ARRIVED_LOADPORT);
        $voyageService->transitionVoyageStatus((int) $voyage->id, VoyageStateMachine::STATUS_TENDERED_NOR, ['nor_timestamp' => $now->toDateTimeString()]);
        $voyageService->transitionVoyageStatus((int) $voyage->id, VoyageStateMachine::STATUS_COMMENCED_LOADING);
        $voyageService->transitionVoyageStatus((int) $voyage->id, VoyageStateMachine::STATUS_COMPLETED_LOADING);
        $voyageService->transitionVoyageStatus((int) $voyage->id, VoyageStateMachine::STATUS_LADEN_PASSAGE, ['bill_of_lading_issued' => true]);

        // 4. Insiden Badai Sumatra Squall di Selat Malaka
        // Gelombang signifikan 4.8m, angin kencang Beaufort 8, deviasi 45 mil laut ke perlindungan Pulau Karimun
        $weatherAdvisory = $voyageService->evaluateWeatherAdvisory(
            (int) $voyage->id,
            'SEVERE_SUMATRA_SQUALL_FORCE_8',
            4.80,
            4.00,
            45.00
        );

        expect($weatherAdvisory->weather_pattern_alert)->toBe('SEVERE_SUMATRA_SQUALL_FORCE_8')
            ->and($weatherAdvisory->significant_wave_height_m)->toBe(4.80);

        $voyageService->acceptWeatherDiversion((int) $weatherAdvisory->id);

        // Catat Noon Report di tengah badai
        $noon = $voyageService->recordNoonReport(
            (int) $voyage->id,
            $now->addDays(1),
            1.2540,
            103.1120,
            120.0,
            8.5,
            180.0,
            8.0, // Beaufort 8 (gale)
            'SW',
            5.0, // Douglas 5 (rough)
            72.0,
            12.5, // High propeller slip
            28.5,
            2.1,
            'Diterjang squall dahsyat Selat Malaka. Kapal berguling keras (heavy rolling), mesin dipaksa olah gerak darurat.'
        );

        expect($noon->wind_force_beaufort)->toBe(8.0)
            ->and($noon->sea_state_douglas)->toBe(5.0);

        // 5. Deklarasi York-Antwerp General Average 2016
        // Akibat badai, propeller mengalami overstrain saat menghindari kandas ($75k sacrifice),
        // kargo kontainer deck dibuang ke laut untuk stabilitas ($120k sacrifice),
        // dan sewa kapal tunda penyelamat darurat di Karimun ($180k expenditure).
        $gaCase = $gaService->declareAndAdjustGeneralAverage(
            (int) $voyage->id,
            'GA-2026-MALACCA-01',
            'Darurat badai Sumatra Squall di Selat Malaka: manuver darurat, jettison muatan dek, dan bantuan kapal tunda penyelamat',
            'Richards Hogg Lindley Maritime Adjusters',
            16000000.0, // Sound value kapal
            0.0,
            12000000.0, // Commercial value kargo
            0.0,
            500000.0,   // Freight at risk
            0.0,
            [
                ['interest' => 'SHIP', 'description' => 'Kerusakan propeller olah gerak darurat', 'amount_usd' => 75000.0, 'category' => 'SACRIFICE'],
                ['interest' => 'CARGO', 'description' => 'Jettison muatan dek demi keselamatan kapal', 'amount_usd' => 120000.0, 'category' => 'SACRIFICE'],
                ['interest' => 'SHIP', 'description' => 'Biaya kapal tunda penyelamat (Salvage tug)', 'amount_usd' => 180000.0, 'category' => 'EXPENDITURE'],
            ]
        );

        expect($gaCase->total_sacrifices_usd)->toBe(195000.0) // 75k + 120k
            ->and($gaCase->total_expenditures_usd)->toBe(180000.0)
            ->and($gaCase->general_average_loss_usd)->toBe(375000.0)
            ->and($gaCase->ship_contribution_usd)->toBeGreaterThan(0.0)
            ->and($gaCase->cargo_contribution_usd)->toBeGreaterThan(0.0);

        // 6. Sengketa Laytime SHEX vs SHINC di Port Bongkar
        // Tiba di Jurong, ada hujan lebat 12 jam yang dipersengketakan
        $portCall = $marineService->schedulePortCall(
            (int) $voyage->id,
            'SGSIN',
            'Jurong Port Singapore',
            'DISCHARGING',
            $now->addDays(2),
            11.5,
            12.0
        );

        $laytimeResult = $charterService->calculateLaytimeAndIssueInvoice(
            (int) $charter->id,
            (int) $portCall->id,
            'SOF-JURONG-MALACCA-01',
            $now->addDays(2),
            $now->addDays(2)->addHours(6),
            $now->addDays(5), // 66 jam
            [
                ['start' => $now->addDays(3), 'end' => $now->addDays(3)->addHours(12), 'reason' => 'RAIN', 'is_excepted' => true],
            ]
        );

        expect($laytimeResult['calculation'])->not->toBeNull()
            ->and($laytimeResult['calculation']->rain_interruption_hours)->toBe(12.0);

        // 7. Pembukuan Buku Besar Double-Entry Seimbang
        $ledgerService->recordBalancedTransaction(
            (int) $voyage->id,
            (int) $vessel->id,
            PortFleetLedgerService::ACC_RECEIVABLE_FREIGHT,
            PortFleetLedgerService::ACC_REVENUE_FREIGHT,
            500000.0,
            'FREIGHT_SETTLEMENT',
            (int) $charter->id,
            'Pengakuan pendapatan kargo Dumai-Singapore'
        );

        $ledgerService->recordBalancedTransaction(
            (int) $voyage->id,
            (int) $vessel->id,
            PortFleetLedgerService::ACC_EXPENSE_GENERAL_AVERAGE,
            PortFleetLedgerService::ACC_PAYABLE_DISBURSEMENTS,
            (float) $gaCase->ship_contribution_usd,
            'GENERAL_AVERAGE_CONTRIBUTION',
            (int) $gaCase->id,
            'Kontribusi kapal atas kerugian General Average Selat Malaka'
        );

        // 8. Audit Integritas Buku Besar
        $audit = $ledgerService->auditLedgerIntegrity((int) $voyage->id);
        expect($audit['is_balanced'])->toBeTrue()
            ->and($audit['corrupted_count'])->toBe(0)
            ->and($audit['total_entries'])->toBe(4);
    });
});
