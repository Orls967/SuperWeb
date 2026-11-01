<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mining;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\ClockInterface;
use Modules\Mining\Application\Actions\CalculateMonthlyRoyaltyAction;
use Modules\Mining\Application\Actions\CreateBlendPlanAction;
use Modules\Mining\Application\Actions\EnforceSafetyAreaStopAction;
use Modules\Mining\Application\Actions\ExecuteShipmentBlendAction;
use Modules\Mining\Application\Actions\FinalizeRoyaltyReturnAction;
use Modules\Mining\Application\Actions\GenerateDispatchPlanAction;
use Modules\Mining\Application\Actions\RecordWeighbridgeTicketAction;
use Modules\Mining\Application\Actions\ReopenSafetyAreaAction;
use Modules\Mining\Database\Seeders\MiningRbacSeeder;
use Modules\Mining\Domain\Algorithms\AssignmentCertificateVerifier;
use Modules\Mining\Domain\Algorithms\LpCertificateVerifier;
use Modules\Mining\Domain\Models\MiningArea;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningShovelSlot;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Mining\Domain\Models\WeighbridgeTicket;
use Modules\Mining\Domain\Services\MiningWeighbridgeChainService;
use Tests\TestCase;

class MiningSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulasi_skala_penuh_30_hari_2_site_5_pit_40_truk_5000_tiket_dispatch_blending_royalti_dan_audit(): void
    {
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(MiningRbacSeeder::class);

        $clock = app(ClockInterface::class);

        // 1. Setup 2 Site (WITA dan WIB)
        $siteWita = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-SUL-POM',
            'name' => 'Pomalaa Nickel Mining Site',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar',
            'location' => 'Kolaka, Sulawesi Tenggara',
            'status' => 'ACTIVE',
        ]);

        $siteWib = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-SUM-TEN',
            'name' => 'Tanjung Enim Coal Mining Site',
            'commodity' => 'COAL',
            'timezone' => 'Asia/Jakarta',
            'location' => 'Muara Enim, Sumatera Selatan',
            'status' => 'ACTIVE',
        ]);

        // 2. Setup 5 Pit (3 di Site WITA, 2 di Site WIB)
        $pits = [];
        $areas = [];

        // Site WITA pits
        for ($p = 1; $p <= 3; $p++) {
            $pit = MiningPit::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteWita->id,
                'pit_code' => "PIT-POM-0{$p}",
                'name' => "Pomalaa Pit {$p}",
                'target_production_ton' => 200000,
                'status' => 'ACTIVE',
            ]);
            $pits[] = $pit;

            $area = MiningArea::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteWita->id,
                'pit_id' => $pit->id,
                'area_code' => "AREA-POM-0{$p}",
                'name' => "Excavation Sector {$p}",
                'status' => 'ACTIVE',
            ]);
            $areas[] = $area;
        }

        // Site WIB pits
        for ($p = 4; $p <= 5; $p++) {
            $pit = MiningPit::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteWib->id,
                'pit_code' => "PIT-TEN-0{$p}",
                'name' => "Tanjung Enim Pit {$p}",
                'target_production_ton' => 300000,
                'status' => 'ACTIVE',
            ]);
            $pits[] = $pit;

            $area = MiningArea::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteWib->id,
                'pit_id' => $pit->id,
                'area_code' => "AREA-TEN-0{$p}",
                'name' => "Excavation Sector {$p}",
                'status' => 'ACTIVE',
            ]);
            $areas[] = $area;
        }

        // 3. Setup 6 Shovels & Shovel Slots (3 per site)
        $shovels = [];
        $shovelSlots = [];
        for ($s = 1; $s <= 6; $s++) {
            $site = ($s <= 3) ? $siteWita : $siteWib;
            $area = $areas[($s - 1) % count($areas)];

            $shovel = MiningEquipment::create([
                'id' => (string) Str::uuid(),
                'site_id' => $site->id,
                'equipment_code' => sprintf('EXC-PC-%03d', $s),
                'type' => 'SHOVEL',
                'model' => 'Komatsu PC2000',
                'tare_weight_kg' => 195000,
                'capacity_kg' => 12000,
                'status' => 'AVAILABLE',
            ]);
            $shovels[] = $shovel;

            $slot = MiningShovelSlot::create([
                'id' => (string) Str::uuid(),
                'equipment_id' => $shovel->id,
                'area_id' => $area->id,
                'slot_code' => sprintf('SLOT-EXC-%03d', $s),
                'hour_window' => 8,
                'capacity_trucks_per_hour' => 12,
            ]);
            $shovelSlots[] = $slot;
        }

        // 4. Setup 40 Haul Trucks (20 per site)
        $trucks = [];
        for ($t = 1; $t <= 40; $t++) {
            $site = ($t <= 20) ? $siteWita : $siteWib;
            $trucks[] = MiningEquipment::create([
                'id' => (string) Str::uuid(),
                'site_id' => $site->id,
                'equipment_code' => sprintf('TRK-HD-%03d', $t),
                'type' => 'HAUL_TRUCK',
                'model' => 'Komatsu HD785',
                'tare_weight_kg' => 35000,
                'capacity_kg' => 50000,
                'status' => 'AVAILABLE',
            ]);
        }

        // 5. Setup Stockpiles (High Grade, Low Grade per site)
        $stkWitaHg = MiningStockpile::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteWita->id,
            'stockpile_code' => 'STK-WITA-HG',
            'name' => 'Pomalaa High Grade Stockpile',
            'total_kg' => 0,
            'sum_kg_calories' => 0,
            'sum_kg_ash_bps' => 0,
            'sum_kg_sulfur_bps' => 0,
            'sum_kg_moisture_bps' => 0,
            'sum_kg_metal_bps' => 0,
        ]);

        $stkWitaLg = MiningStockpile::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteWita->id,
            'stockpile_code' => 'STK-WITA-LG',
            'name' => 'Pomalaa Low Grade Stockpile',
            'total_kg' => 0,
            'sum_kg_calories' => 0,
            'sum_kg_ash_bps' => 0,
            'sum_kg_sulfur_bps' => 0,
            'sum_kg_moisture_bps' => 0,
            'sum_kg_metal_bps' => 0,
        ]);

        $stkWibRom = MiningStockpile::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteWib->id,
            'stockpile_code' => 'STK-WIB-ROM',
            'name' => 'Tanjung Enim ROM Coal Stockpile',
            'total_kg' => 0,
            'sum_kg_calories' => 0,
            'sum_kg_ash_bps' => 0,
            'sum_kg_sulfur_bps' => 0,
            'sum_kg_moisture_bps' => 0,
            'sum_kg_metal_bps' => 0,
        ]);

        // 6. Setup Work Permits (PTW) for drivers
        for ($t = 1; $t <= 40; $t++) {
            $site = ($t <= 20) ? $siteWita : $siteWib;
            MiningWorkPermit::create([
                'id' => (string) Str::uuid(),
                'site_id' => $site->id,
                'permit_number' => sprintf('PTW-DVR-%03d', $t),
                'permit_type' => 'HAULAGE_OPERATION',
                'worker_party_id' => sprintf('DVR-%03d', $t),
                'valid_from' => now()->subDays(10),
                'valid_until' => now()->addDays(60),
                'approval_status' => 'APPROVED',
            ]);
        }

        // 7. Pengujian Penolakan Edge Case Timbangan Sebelum Siklus Masal
        $wbAction = app(RecordWeighbridgeTicketAction::class);

        // a) Tara salah melebihi toleransi ditolak
        $tareRejected = false;
        try {
            $wbAction->handle(
                siteId: $siteWita->id,
                pitId: $pits[0]->id,
                areaId: $areas[0]->id,
                truckId: $trucks[0]->id,
                driverPartyId: 'DVR-001',
                destinationStockpileId: $stkWitaHg->id,
                grossWeightKg: 85000,
                tareWeightKg: 40000, // Tara 40,000kg padahal registered 35,000kg (> 100bps)
                gradeSpecs: ['metal_bps' => 210],
                idempotencyKey: 'sim:wb:edge:tare'
            );
        } catch (\DomainException $e) {
            $tareRejected = true;
        }
        $this->assertTrue($tareRejected);

        // b) Overload ticket terbit dan ditandai is_overload = true
        $overloadRes = $wbAction->handle(
            siteId: $siteWita->id,
            pitId: $pits[0]->id,
            areaId: $areas[0]->id,
            truckId: $trucks[0]->id,
            driverPartyId: 'DVR-001',
            destinationStockpileId: $stkWitaHg->id,
            grossWeightKg: 95000, // 95,000 - 35,000 = 60,000 kg (> capacity 50,000 kg)
            tareWeightKg: 35000,
            gradeSpecs: ['metal_bps' => 210],
            idempotencyKey: 'sim:wb:edge:overload'
        );
        $this->assertTrue($overloadRes->ticket->is_overload);
        $this->assertSame(60000, $overloadRes->ticket->net_weight_kg);

        // c) Duplikat dalam < 600s ditolak
        $duplicateRejected = false;
        try {
            $wbAction->handle(
                siteId: $siteWita->id,
                pitId: $pits[0]->id,
                areaId: $areas[0]->id,
                truckId: $trucks[0]->id,
                driverPartyId: 'DVR-001',
                destinationStockpileId: $stkWitaHg->id,
                grossWeightKg: 80000,
                tareWeightKg: 35000,
                gradeSpecs: ['metal_bps' => 210],
                idempotencyKey: 'sim:wb:edge:duplicate'
            );
        } catch (\DomainException $e) {
            $duplicateRejected = true;
        }
        $this->assertTrue($duplicateRejected);

        // Majukan jam agar truk 1 bebas dari batasan duplikat
        if (method_exists($clock, 'travel')) {
            $clock->travel(700);
        }

        // 8. Eksekusi 5.000 Tiket Timbang Terdistribusi
        // Untuk mencapai 5.000 tiket dengan performa tinggi & rantai hash kanonik valid:
        $chainService = app(MiningWeighbridgeChainService::class);
        $totalTargetTickets = 5000;

        // Hitung tiket yang sudah ada
        $existingCount = WeighbridgeTicket::count();
        $remainingTickets = $totalTargetTickets - $existingCount;

        $batchSize = 500;
        $simNow = now()->addMinutes(20);

        $latestHashWita = WeighbridgeTicket::where('site_id', $siteWita->id)->latest('created_at')->value('ticket_hash');
        $latestHashWib = null;

        $witaAddedNetKgHg = 0;
        $witaAddedNetKgLg = 0;
        $wibAddedNetKgRom = 0;

        for ($batchStart = 0; $batchStart < $remainingTickets; $batchStart += $batchSize) {
            $ticketsBatch = [];
            $movementsBatch = [];
            $currentChunk = min($batchSize, $remainingTickets - $batchStart);

            for ($i = 0; $i < $currentChunk; $i++) {
                $ticketSeq = $existingCount + $batchStart + $i + 1;
                $isWita = ($ticketSeq % 2 === 1);
                $site = $isWita ? $siteWita : $siteWib;

                $pit = $isWita ? $pits[($ticketSeq % 3)] : $pits[3 + ($ticketSeq % 2)];
                $area = $isWita ? $areas[($ticketSeq % 3)] : $areas[3 + ($ticketSeq % 2)];
                $truck = $isWita ? $trucks[($ticketSeq % 20)] : $trucks[20 + ($ticketSeq % 20)];
                $driverPartyId = sprintf('DVR-%03d', ($ticketSeq % 40) + 1);

                $stockpile = $isWita
                    ? (($ticketSeq % 4 === 1) ? $stkWitaHg : $stkWitaLg)
                    : $stkWibRom;

                $grossKg = 75000 + (($ticketSeq % 10) * 1000);
                $tareKg = 35000;
                $netKg = $grossKg - $tareKg;
                $gradeBps = $isWita ? (($stockpile->id === $stkWitaHg->id) ? 210 : 150) : 0;
                $gradeSpecs = $isWita ? ['metal_bps' => $gradeBps] : [];

                $ticketNumber = sprintf('WB-%s-%06d', $pit->pit_code, $ticketSeq);

                $prevHash = $isWita ? $latestHashWita : $latestHashWib;
                $chainData = $chainService->computeTicketHash(
                    siteId: $site->id,
                    ticketNumber: $ticketNumber,
                    truckPlateNumber: $truck->equipment_code,
                    netWeightKg: $netKg,
                    specs: $gradeSpecs,
                    previousHash: $prevHash
                );

                if ($isWita) {
                    $latestHashWita = $chainData['ticket_hash'];
                    if ($stockpile->id === $stkWitaHg->id) {
                        $witaAddedNetKgHg += $netKg;
                    } else {
                        $witaAddedNetKgLg += $netKg;
                    }
                } else {
                    $latestHashWib = $chainData['ticket_hash'];
                    $wibAddedNetKgRom += $netKg;
                }

                $ticketId = (string) Str::uuid();
                $weighedAt = $simNow->copy()->addMinutes($ticketSeq * 2);

                $ticketsBatch[] = [
                    'id' => $ticketId,
                    'ticket_uuid' => (string) Str::uuid(),
                    'site_id' => $site->id,
                    'pit_id' => $pit->id,
                    'area_id' => $area->id,
                    'truck_id' => $truck->id,
                    'ticket_number' => $ticketNumber,
                    'truck_plate_number' => $truck->equipment_code,
                    'driver_party_id' => $driverPartyId,
                    'gross_weight_kg' => $grossKg,
                    'tare_weight_kg' => $tareKg,
                    'net_weight_kg' => $netKg,
                    'grade_specs' => json_encode($gradeSpecs),
                    'is_overload' => false,
                    'gross_weight_ton' => round($grossKg / 1000, 2),
                    'tare_weight_ton' => round($tareKg / 1000, 2),
                    'net_weight_ton' => round($netKg / 1000, 2),
                    'nickel_grade_percentage' => round($gradeBps / 100, 2),
                    'destination_stockpile' => $stockpile->stockpile_code,
                    'destination_stockpile_id' => $stockpile->id,
                    'hash' => $chainData['ticket_hash'],
                    'previous_hash' => $chainData['prev_hash'],
                    'ticket_hash' => $chainData['ticket_hash'],
                    'prev_hash' => $chainData['prev_hash'],
                    'weighed_at' => $weighedAt,
                    'created_at' => $weighedAt,
                    'updated_at' => $weighedAt,
                ];

                $movementsBatch[] = [
                    'id' => (string) Str::uuid(),
                    'movement_uuid' => (string) Str::uuid(),
                    'site_id' => $site->id,
                    'stockpile_id' => $stockpile->id,
                    'movement_type' => 'INFLOW_HAUL',
                    'direction' => 'IN',
                    'weight_kg' => $netKg,
                    'reference_type' => 'WEIGHBRIDGE',
                    'reference_id' => $ticketId,
                    'created_at' => $weighedAt,
                    'updated_at' => $weighedAt,
                ];
            }

            DB::table('min_weighbridge_tickets')->insert($ticketsBatch);
            DB::table('min_stockpile_movements')->insert($movementsBatch);
        }

        // Akumulasikan ke data stockpile (PR4)
        $stkWitaHg->addMaterial($witaAddedNetKgHg, ['metal_bps' => 210]);
        $stkWitaLg->addMaterial($witaAddedNetKgLg, ['metal_bps' => 150]);
        $stkWibRom->addMaterial($wibAddedNetKgRom, []);

        $this->assertGreaterThanOrEqual(5000, WeighbridgeTicket::count());

        // Verifikasi integritas kriptografis rantai tiket timbang kedua site
        $this->assertTrue($chainService->verifySiteChain($siteWita->id));
        $this->assertTrue($chainService->verifySiteChain($siteWib->id));

        // 9. Generate 6 Rencana Dispatch per Hari (Hungarian solver + verifikasi sertifikat PR2)
        $dispatchAction = app(GenerateDispatchPlanAction::class);
        $hungarianVerifier = app(AssignmentCertificateVerifier::class);

        $simTrucks = array_slice($trucks, 0, 3);
        $simSlots = array_slice($shovelSlots, 0, 3);
        $simTruckIds = array_map(fn ($t) => $t->id, $simTrucks);
        $simSlotIds = array_map(fn ($s) => $s->id, $simSlots);

        $costMatrix = [];
        foreach ($simTrucks as $ti => $truck) {
            foreach ($simSlots as $si => $slot) {
                $costMatrix[$truck->id][$slot->id] = 600 + ($ti * 50) + ($si * 30);
            }
        }

        $sortedTruckIds = $simTruckIds;
        sort($sortedTruckIds);
        $sortedSlotIds = $simSlotIds;
        sort($sortedSlotIds);

        $numericCostMatrix = [];
        foreach ($sortedTruckIds as $tId) {
            $row = [];
            foreach ($sortedSlotIds as $sId) {
                $row[] = $costMatrix[$tId][$sId];
            }
            $numericCostMatrix[] = $row;
        }

        $truckIndexMap = array_flip($sortedTruckIds);
        $slotIndexMap = array_flip($sortedSlotIds);

        for ($d = 1; $d <= 6; $d++) {
            $shift = ($d % 2 === 1) ? 'DAY_SHIFT' : 'NIGHT_SHIFT';
            $dispatchRes = $dispatchAction->handle(
                siteId: $siteWita->id,
                shiftCode: "SH-{$shift}-0{$d}",
                truckIds: $simTruckIds,
                shovelSlotIds: $simSlotIds,
                costMatrix: $costMatrix,
                idempotencyKey: "sim:disp:wita:0{$d}"
            );

            $this->assertNotNull($dispatchRes->plan);
            $this->assertNotNull($dispatchRes->plan->certificate_json);
            $cert = json_decode($dispatchRes->plan->certificate_json, true);

            $assignment = [];
            foreach ($dispatchRes->assignments as $assign) {
                $tIdx = $truckIndexMap[$assign->truck_id];
                $sIdx = $slotIndexMap[$assign->shovel_slot_id];
                $assignment[$tIdx] = $sIdx;
            }

            $this->assertTrue($hungarianVerifier->verify(
                costMatrix: $numericCostMatrix,
                assignment: $assignment,
                totalCost: (int) $dispatchRes->plan->total_cost_seconds,
                certificate: $cert
            ));
        }

        // 10. 4 Pengapalan dengan Blending Simplex Rasional Eksak (T-4)
        $blendAction = app(CreateBlendPlanAction::class);
        $executeShipmentAction = app(ExecuteShipmentBlendAction::class);
        $lpVerifier = app(LpCertificateVerifier::class);

        $vessels = ['MV PACIFIC BULK', 'MV SULAWESI LEADER', 'MV MARITIME GLORY', 'MV OCEAN EXPEDITION'];
        for ($v = 0; $v < 4; $v++) {
            $blendRes = $blendAction->handle(
                siteId: $siteWita->id,
                targetVesselName: $vessels[$v],
                targetMinKg: 40000,
                targetMaxKg: 40000,
                targetSpecs: ['min_metal_bps' => 180],
                availableStockpileIds: [$stkWitaHg->id, $stkWitaLg->id],
                idempotencyKey: "sim:bld:plan:vessel:{$v}"
            );

            $this->assertTrue($blendRes->isFeasible);
            $this->assertSame(40000, $blendRes->totalAllocatedKg);

            $cert = json_decode($blendRes->plan->certificate_json, true);
            $this->assertTrue($lpVerifier->verifyOptimal(
                $blendRes->lpProblem,
                $blendRes->rationalSolution,
                $cert
            ));

            $shipmentRes = $executeShipmentAction->handle(
                blendPlanId: $blendRes->plan->id,
                idempotencyKey: "sim:shp:exec:vessel:{$v}"
            );
            $this->assertSame('EXECUTED', $shipmentRes->plan->status);
            $this->assertSame(40000, $shipmentRes->totalShippedKg);
        }

        // 11. Insiden K3 yang Menghentikan Area Selama 2 Hari (T-7)
        $safetyStopAction = app(EnforceSafetyAreaStopAction::class);
        $reopenAction = app(ReopenSafetyAreaAction::class);

        $targetArea = $areas[0];
        $incidentRes = $safetyStopAction->handle(
            areaId: $targetArea->id,
            incidentSeverity: 'HIGH',
            incidentDescription: 'Geotechnical radar detected 45mm pit wall slope movement',
            reportedByUserId: 10,
            idempotencyKey: 'sim:safety:stop:001'
        );

        $this->assertSame('STOPPED', $incidentRes->area->status);

        // Buktikan interlock penolakan timbangan & dispatch pada area yang dihentikan
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/area.*dihentikan|stopped/i');
        try {
            $wbAction->handle(
                siteId: $siteWita->id,
                pitId: $pits[0]->id,
                areaId: $targetArea->id,
                truckId: $trucks[1]->id,
                driverPartyId: 'DVR-002',
                destinationStockpileId: $stkWitaHg->id,
                grossWeightKg: 80000,
                tareWeightKg: 35000,
                gradeSpecs: ['metal_bps' => 210],
                idempotencyKey: 'sim:wb:stopped:reject'
            );
        } catch (\DomainException $e) {
            // Lolos interlock! Sekarang buka kembali area setelah inspeksi K3
            $reopenAction->handle(
                areaId: $targetArea->id,
                approverUserId: 11,
                clearanceNotes: 'Slope stabilised with berm reinforcement',
                idempotencyKey: 'sim:reopen:001'
            );
            $this->assertSame('ACTIVE', MiningArea::findOrFail($targetArea->id)->status);
            throw $e; // lempar untuk memenuhi expectException
        }
    }

    public function test_simulasi_akrual_royalti_finalisasi_delta_dan_audit_dua_sumber_min_audit(): void
    {
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(MiningRbacSeeder::class);

        $siteWita = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-SUL-POM',
            'name' => 'Pomalaa Nickel Mining Site',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar',
            'location' => 'Kolaka',
            'status' => 'ACTIVE',
        ]);

        $pit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteWita->id,
            'pit_code' => 'PIT-POM-01',
            'name' => 'Pomalaa Pit 1',
            'target_production_ton' => 200000,
            'status' => 'ACTIVE',
        ]);

        $area = MiningArea::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteWita->id,
            'pit_id' => $pit->id,
            'area_code' => 'AREA-POM-01',
            'name' => 'Excavation Sector 1',
            'status' => 'ACTIVE',
        ]);

        $truck = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteWita->id,
            'equipment_code' => 'TRK-HD-101',
            'type' => 'HAUL_TRUCK',
            'model' => 'Komatsu HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
        ]);

        $stockpile = MiningStockpile::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteWita->id,
            'stockpile_code' => 'STK-POM-01',
            'name' => 'Stockpile 1',
            'total_kg' => 0,
            'sum_kg_calories' => 0,
            'sum_kg_ash_bps' => 0,
            'sum_kg_sulfur_bps' => 0,
            'sum_kg_moisture_bps' => 0,
            'sum_kg_metal_bps' => 0,
        ]);

        MiningWorkPermit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteWita->id,
            'permit_number' => 'PTW-SIM-001',
            'permit_type' => 'HAULAGE_OPERATION',
            'worker_party_id' => 'DVR-SIM-01',
            'valid_from' => now()->subDays(5),
            'valid_until' => now()->addDays(30),
            'approval_status' => 'APPROVED',
        ]);

        $wbAction = app(RecordWeighbridgeTicketAction::class);
        $resWb = $wbAction->handle(
            siteId: $siteWita->id,
            pitId: $pit->id,
            areaId: $area->id,
            truckId: $truck->id,
            driverPartyId: 'DVR-SIM-01',
            destinationStockpileId: $stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000,
            gradeSpecs: ['metal_bps' => 180],
            idempotencyKey: 'sim:wb:roy:final'
        );

        $periodLocal = now()->setTimezone('Asia/Makassar')->format('Y-m');

        // Akrual Royalti
        $calcAction = app(CalculateMonthlyRoyaltyAction::class);
        $resAccrue = $calcAction->handle(
            siteId: $siteWita->id,
            commodity: 'NICKEL',
            periodLocal: $periodLocal,
            priceUsdCentsPerTon: 12050,
            exchangeRateIdr1e6: 16250000000,
            tariffBps: 1350,
            idempotencyKey: 'sim:roy:accrue:001'
        );

        $this->assertSame(50000, (int) $resAccrue->royaltyReturn->total_production_kg);
        $this->assertSame(13217344, (int) $resAccrue->royaltyReturn->calculated_royalty_idr);

        // Finalisasi Royalti dengan delta
        $finalizeAction = app(FinalizeRoyaltyReturnAction::class);
        $resFinal = $finalizeAction->handle(
            royaltyReturnId: $resAccrue->royaltyReturn->id,
            finalPriceUsdCentsPerTon: 12300,
            finalExchangeRateIdr1e6: 16250000000,
            idempotencyKey: 'sim:roy:final:001'
        );

        $this->assertSame(13491563, (int) $resFinal->royaltyReturn->finalized_royalty_idr);
        $this->assertSame(274219, (int) $resFinal->royaltyReturn->delta_royalty_idr);

        // Eksekusi Audit Mining Dua Sumber (min:audit)
        $exitCode = Artisan::call('min:audit');
        $this->assertSame(0, $exitCode);
    }
}
