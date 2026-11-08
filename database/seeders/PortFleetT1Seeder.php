<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Port\Application\Services\CommercialCharterService;
use Modules\Port\Application\Services\GeneralAverageAdjustmentService;
use Modules\Port\Application\Services\MarineServicesDisbursementService;
use Modules\Port\Application\Services\PortFleetLedgerService;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Application\Services\VoyageExecutionService;
use Modules\Port\Domain\Models\FleetClass;
use Modules\Port\Domain\StateMachines\CharterPartyStateMachine;
use Modules\Port\Domain\StateMachines\VoyageStateMachine;

final class PortFleetT1Seeder extends Seeder
{
    public function run(): void
    {
        $fleetService = app(PortFleetService::class);
        $voyageService = app(VoyageExecutionService::class);
        $charterService = app(CommercialCharterService::class);
        $marineService = app(MarineServicesDisbursementService::class);
        $gaService = app(GeneralAverageAdjustmentService::class);
        $ledgerService = app(PortFleetLedgerService::class);

        // 1. Klasifikasi Kapal (Biro Klasifikasi Indonesia & DNV)
        $bki = FleetClass::firstOrCreate(
            ['code' => 'BKI'],
            [
                'society_name' => 'Biro Klasifikasi Indonesia',
                'class_notation' => 'A100 (I) P Bulk Carrier ESP, SM',
                'last_special_survey' => '2024-05-10',
                'next_special_survey' => '2029-05-10',
                'last_drydock_survey' => '2024-05-10',
                'next_drydock_survey' => '2027-05-10',
                'status' => 'ACTIVE',
            ]
        );

        $dnv = FleetClass::firstOrCreate(
            ['code' => 'DNV'],
            [
                'society_name' => 'DNV Maritime',
                'class_notation' => '1A Container Ship BIS BWM(T) Clean',
                'last_special_survey' => '2023-08-15',
                'next_special_survey' => '2028-08-15',
                'last_drydock_survey' => '2023-08-15',
                'next_drydock_survey' => '2026-08-15',
                'status' => 'ACTIVE',
            ]
        );

        // 2. Daftarkan 5 Kapal Niaga
        // Kapal 1: Supramax Bulk Carrier
        $mvNusantara = $fleetService->registerVessel([
            'imo_number' => '9876543',
            'vessel_name' => 'MV Nusantara Perdana',
            'call_sign' => 'YDKP',
            'mmsi' => '525012345',
            'flag_state' => 'ID',
            'home_port' => 'Tanjung Priok',
            'vessel_type' => 'BULK_CARRIER',
            'fleet_class_code' => 'BKI',
            'built_year' => 2021,
            'deadweight_tonnage' => 58000,
            'gross_tonnage' => 34500,
            'net_tonnage' => 19800,
            'length_overall_cm' => 19990,
            'beam_cm' => 3226,
            'design_draft_cm' => 1300,
            'main_engine_power_kw' => 8800,
            'design_speed_knots' => 14.20,
            'sfoc_design_grams_kwh' => 172.0,
            'fuel_types_supported' => 'VLSFO,LSMGO',
        ]);

        // Kapal 2: Container Feeder 1800 TEU
        $mvMeratus = $fleetService->registerVessel([
            'imo_number' => '9881122',
            'vessel_name' => 'MV Khatulistiwa Express',
            'call_sign' => 'PKME',
            'mmsi' => '525098765',
            'flag_state' => 'ID',
            'home_port' => 'Surabaya',
            'vessel_type' => 'CONTAINER',
            'fleet_class_code' => 'DNV',
            'built_year' => 2022,
            'deadweight_tonnage' => 24000,
            'gross_tonnage' => 18500,
            'net_tonnage' => 9200,
            'length_overall_cm' => 17200,
            'beam_cm' => 2840,
            'design_draft_cm' => 950,
            'main_engine_power_kw' => 12500,
            'design_speed_knots' => 18.50,
            'sfoc_design_grams_kwh' => 180.0,
            'fuel_types_supported' => 'VLSFO,LSMGO',
        ]);

        // Kapal 3: Product Tanker Medium Range (MR)
        $mvOcean = $fleetService->registerVessel([
            'imo_number' => '9792233',
            'vessel_name' => 'MT Samudera Bahari',
            'call_sign' => 'POBC',
            'mmsi' => '525045678',
            'flag_state' => 'ID',
            'home_port' => 'Balikpapan',
            'vessel_type' => 'PRODUCT_TANKER',
            'fleet_class_code' => 'BKI',
            'built_year' => 2020,
            'deadweight_tonnage' => 49999,
            'gross_tonnage' => 29800,
            'net_tonnage' => 15200,
            'length_overall_cm' => 18300,
            'beam_cm' => 3220,
            'design_draft_cm' => 1250,
            'main_engine_power_kw' => 9200,
            'design_speed_knots' => 14.80,
            'sfoc_design_grams_kwh' => 174.0,
            'fuel_types_supported' => 'VLSFO,LSMGO',
        ]);

        // 3. Konfigurasi Tangki Bunker Kapal & ROB
        $fleetService->configureBunkerTank((int) $mvNusantara->id, 'TK-1P', 'No. 1 Heavy Fuel Tank Port', 'VLSFO', 650.0, 480.0);
        $fleetService->configureBunkerTank((int) $mvNusantara->id, 'TK-1S', 'No. 1 Heavy Fuel Tank Stbd', 'VLSFO', 650.0, 470.0);
        $fleetService->configureBunkerTank((int) $mvNusantara->id, 'TK-DO', 'Marine Gas Oil Tank', 'LSMGO', 220.0, 110.0);

        $fleetService->configureBunkerTank((int) $mvMeratus->id, 'TK-C1', 'Container VLSFO Bunker Tank', 'VLSFO', 850.0, 620.0);
        $fleetService->configureBunkerTank((int) $mvMeratus->id, 'TK-CDO', 'Container LSMGO Bunker Tank', 'LSMGO', 250.0, 135.0);

        $fleetService->configureBunkerTank((int) $mvOcean->id, 'TK-T1', 'Tanker VLSFO Bunker Tank', 'VLSFO', 1100.0, 890.0);

        // 4. Sertifikat Statutoria & Safe Manning
        $now = CarbonImmutable::now();
        $fleetService->recordCertificate((int) $mvNusantara->id, 'SAFETY_CONSTRUCTION', 'SC-2024-BKI-001', 'BKI', $now->subMonths(6), $now->addYears(4));
        $fleetService->recordCertificate((int) $mvNusantara->id, 'SAFETY_EQUIPMENT', 'SE-2024-BKI-002', 'BKI', $now->subMonths(6), $now->addYears(2));
        $fleetService->recordCertificate((int) $mvNusantara->id, 'IOPP', 'IOPP-2024-BKI-003', 'BKI', $now->subMonths(6), $now->addYears(4));
        $fleetService->recordCertificate((int) $mvNusantara->id, 'IAPP', 'IAPP-2024-BKI-004', 'BKI', $now->subMonths(6), $now->addYears(4));
        $fleetService->recordCertificate((int) $mvNusantara->id, 'SMC', 'SMC-2024-HUBLA-001', 'Ditjen Perhubungan Laut', $now->subMonths(6), $now->addYears(3));
        $fleetService->recordSafeManning((int) $mvNusantara->id, 'SMD-ID-2024-098', 4, 4, 6, 4, 2, 21);

        $fleetService->recordCertificate((int) $mvMeratus->id, 'SAFETY_CONSTRUCTION', 'SC-2024-DNV-101', 'DNV', $now->subMonths(3), $now->addYears(4));
        $fleetService->recordCertificate((int) $mvMeratus->id, 'IOPP', 'IOPP-2024-DNV-102', 'DNV', $now->subMonths(3), $now->addYears(4));
        $fleetService->recordSafeManning((int) $mvMeratus->id, 'SMD-ID-2024-112', 4, 4, 6, 4, 2, 20);

        // 5. Fixture Charter Party & Kontrak Muatan
        $charterParty = $charterService->createCharterParty([
            'fixture_number' => 'CP-2026-NP-001',
            'vessel_id' => (int) $mvNusantara->id,
            'charterer_name' => 'PT Semen Nusantara Logistik',
            'charter_type' => 'VOYAGE_CHARTER',
            'charter_form' => 'GENCON_1994',
            'laycan_commencement' => $now->subDays(5),
            'laycan_cancellation' => $now->addDays(2),
            'freight_or_hire_rate' => 14.50, // USD/MT
            'laytime_allowed_hours' => 72.0,
            'laytime_terms' => 'WWD_SHINC',
            'demurrage_rate_daily' => 16500.0,
            'despatch_rate_daily' => 8250.0,
        ]);

        $charterService->transitionStatus((int) $charterParty->id, CharterPartyStateMachine::STATUS_FIXTURE_AGREED);

        $charterService->createFreightContract(
            (int) $charterParty->id,
            'COA-2026-CLINKER-01',
            'Clinker Bulk in Hold 1-5',
            50000.0,
            14.50,
            5.0,
            250000.0 // Advance freight paid
        );

        // 6. Rencana & Pelaksanaan Pelayaran (Voyage Execution)
        $voyage = $voyageService->planVoyage([
            'voyage_code' => 'VOY-2026-N01',
            'vessel_id' => (int) $mvNusantara->id,
            'charter_party_id' => (int) $charterParty->id,
            'voyage_number' => 'VOY-01/2026',
            'origin_port' => 'IDTKG', // Tarahan Lampung
            'destination_port' => 'IDSUB', // Tanjung Perak
            'load_port' => 'Pelabuhan Panjang Lampung',
            'discharge_port' => 'Dermaga Jamrud Tanjung Perak',
            'planned_start_at' => $now->subDays(4),
            'planned_end_at' => $now->addDays(3),
            'planned_distance_nm' => 480.0,
            'planned_speed_knots' => 12.5,
        ]);

        $voyageService->transitionVoyageStatus((int) $voyage->id, VoyageStateMachine::STATUS_BALLAST_PASSAGE);
        $voyageService->transitionVoyageStatus((int) $voyage->id, VoyageStateMachine::STATUS_ARRIVED_LOADPORT);
        $voyageService->transitionVoyageStatus((int) $voyage->id, VoyageStateMachine::STATUS_TENDERED_NOR, [
            'nor_timestamp' => $now->subDays(3)->toDateTimeString(),
        ]);
        $voyageService->transitionVoyageStatus((int) $voyage->id, VoyageStateMachine::STATUS_COMMENCED_LOADING);
        $voyageService->transitionVoyageStatus((int) $voyage->id, VoyageStateMachine::STATUS_COMPLETED_LOADING);
        $voyageService->transitionVoyageStatus((int) $voyage->id, VoyageStateMachine::STATUS_LADEN_PASSAGE, [
            'bill_of_lading_issued' => true,
        ]);

        // Leg pelayaran
        $voyageService->addVoyageLeg((int) $voyage->id, 1, 'BALLAST', 'Jakarta Anchorage', 'Tarahan Jetty', 120.0, 13.0);
        $voyageService->addVoyageLeg((int) $voyage->id, 2, 'LADEN', 'Tarahan Jetty', 'Tanjung Perak Port', 360.0, 12.5);

        // Noon Reports harian
        $voyageService->recordNoonReport(
            (int) $voyage->id,
            $now->subDays(2),
            -5.8520,
            106.1240,
            95.0,
            12.8,
            285.0,
            3.0,
            'SE',
            2.0,
            82.0,
            4.2,
            24.5,
            1.8,
            'Pelayaran normal Selat Sunda menuju Laut Jawa, cuaca cerah berawan.'
        );

        $voyageService->recordNoonReport(
            (int) $voyage->id,
            $now->subDays(1),
            -6.4210,
            110.3540,
            102.0,
            12.4,
            278.0,
            4.0,
            'E',
            2.5,
            83.5,
            5.1,
            25.8,
            1.9,
            'Perairan utara Semarang, arus laut melawan 0.8 knots.'
        );

        // 7. Port Call, Layanan Maritim & Jendela Dermaga
        $portCall = $marineService->schedulePortCall(
            (int) $voyage->id,
            'IDSUB',
            'Tanjung Perak Surabaya',
            'DISCHARGING',
            $now->addHours(12),
            11.20,
            12.10
        );

        $marineService->reserveCallWindow(
            (int) $portCall->id,
            'BERTH-SUB-01',
            $now->addHours(12),
            $now->addDays(3),
            220.0,
            13.5
        );

        $marineService->requestPilotage((int) $portCall->id, 'INWARD', 1450.0, 'Capt. Hendro');
        $marineService->assignTugboat((int) $portCall->id, 'TB Bima V', 50, 2, 750.0, 'BERTHING');
        $marineService->assignTugboat((int) $portCall->id, 'TB Arjuna III', 45, 2, 700.0, 'BERTHING');

        // Operasi Bunkering
        $marineService->recordBunkeringOperation(
            (int) $mvNusantara->id,
            (int) $portCall->id,
            'STEM-SUB-2026-001',
            'PT Pertamina Patra Niaga',
            'OB Jayakarta IV',
            'VLSFO',
            250.0,
            991.2,
            0.46,
            375.0,
            'BDN-SUB-98712',
            'SEAL-SUB-2026-MARPOL-09'
        );

        // Proforma Disbursement Account (PDA)
        $marineService->generateProformaDisbursement(
            (int) $portCall->id,
            'PDA-SUB-2026-NP01',
            4500.0, // port dues
            1450.0, // pilotage
            2900.0, // towage (2 tugs)
            3200.0, // berth hire
            450.0,
            600.0,
            2500.0,
            800.0
        );

        // 8. Hitung Laytime di Port Muat & Terbitkan Demurrage Invoice
        $charterService->calculateLaytimeAndIssueInvoice(
            (int) $charterParty->id,
            (int) $portCall->id,
            'SOF-TARAHAN-001',
            $now->subDays(4)->setHour(8)->setMinute(0),
            $now->subDays(4)->setHour(14)->setMinute(0),
            $now->subDays(1)->setHour(10)->setMinute(0),
            [
                ['start' => $now->subDays(3)->setHour(13)->setMinute(0), 'end' => $now->subDays(3)->setHour(17)->setMinute(0), 'reason' => 'RAIN', 'is_excepted' => true],
            ]
        );

        // 9. Buku Besar Armada (Double-Entry Ledger)
        $ledgerService->recordBalancedTransaction(
            (int) $voyage->id,
            (int) $mvNusantara->id,
            PortFleetLedgerService::ACC_RECEIVABLE_FREIGHT,
            PortFleetLedgerService::ACC_REVENUE_FREIGHT,
            725000.0, // Freight 50,000 MT * $14.50
            'CHARTER_FREIGHT_CONTRACT',
            (int) $charterParty->id,
            'Pengakuan pendapatan uang tambang kargo Clinker 50.000 MT'
        );

        $ledgerService->recordBalancedTransaction(
            (int) $voyage->id,
            (int) $mvNusantara->id,
            PortFleetLedgerService::ACC_EXPENSE_BUNKER,
            PortFleetLedgerService::ACC_PAYABLE_DISBURSEMENTS,
            162500.0, // Bunker 250 MT * $650
            'BUNKERING_OPERATION',
            (int) $portCall->id,
            'Pembelian bahan bakar bunker VLSFO 250 MT di Pelabuhan Tanjung Perak'
        );

        $ledgerService->recordBalancedTransaction(
            (int) $voyage->id,
            (int) $mvNusantara->id,
            PortFleetLedgerService::ACC_EXPENSE_PORT_DUES,
            PortFleetLedgerService::ACC_PAYABLE_DISBURSEMENTS,
            16400.0, // Total PDA
            'PORT_DISBURSEMENT_PDA',
            (int) $portCall->id,
            'Alokasi estimasi pengeluaran pelabuhan (PDA) Tanjung Perak'
        );
    }
}
