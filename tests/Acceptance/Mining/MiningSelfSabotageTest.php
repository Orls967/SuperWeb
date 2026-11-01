<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mining;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Application\Actions\CalculateMonthlyRoyaltyAction;
use Modules\Mining\Application\Actions\RecordWeighbridgeTicketAction;
use Modules\Mining\Database\Seeders\MiningRbacSeeder;
use Modules\Mining\Domain\Algorithms\AssignmentCertificateVerifier;
use Modules\Mining\Domain\Algorithms\LpCertificateVerifier;
use Modules\Mining\Domain\Models\MiningArea;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningRoyaltyReturn;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Mining\Domain\Models\WeighbridgeTicket;
use Tests\TestCase;

class MiningSelfSabotageTest extends TestCase
{
    use RefreshDatabase;

    private MiningSite $site;

    private MiningPit $pit;

    private MiningArea $area;

    private MiningEquipment $truck;

    private MiningStockpile $stockpile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(MiningRbacSeeder::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-SAB-01',
            'name' => 'Sabotage Target Mining Site',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar',
            'location' => 'Kolaka',
            'status' => 'ACTIVE',
        ]);

        $this->pit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_code' => 'PIT-SAB-01',
            'name' => 'Sabotage Pit',
            'target_production_ton' => 100000,
            'status' => 'ACTIVE',
        ]);

        $this->area = MiningArea::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_id' => $this->pit->id,
            'area_code' => 'AREA-SAB-01',
            'name' => 'Sabotage Excavation Area',
            'status' => 'ACTIVE',
        ]);

        $this->truck = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-SAB-01',
            'type' => 'HAUL_TRUCK',
            'model' => 'Komatsu HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
        ]);

        $this->stockpile = MiningStockpile::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'stockpile_code' => 'STK-SAB-01',
            'name' => 'Sabotage Target Stockpile',
            'total_kg' => 0,
            'sum_kg_calories' => 0,
            'sum_kg_ash_bps' => 0,
            'sum_kg_sulfur_bps' => 0,
            'sum_kg_moisture_bps' => 0,
            'sum_kg_metal_bps' => 0,
        ]);

        MiningWorkPermit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'permit_number' => 'PTW-SAB-001',
            'permit_type' => 'HAULAGE_OPERATION',
            'worker_party_id' => 'DVR-SAB-01',
            'valid_from' => now()->subDays(5),
            'valid_until' => now()->addDays(30),
            'approval_status' => 'APPROVED',
        ]);
    }

    public function test_sabotase_1_biaya_hungarian_dibalik_tanda_ditolak_verifier(): void
    {
        $verifier = app(AssignmentCertificateVerifier::class);

        $costMatrix = [
            [500, 800],
            [600, 400],
        ];
        $assignment = [0 => 0, 1 => 1]; // 500 + 400 = 900
        $totalCost = 900;

        // Sabotase: Balik tanda potensial dual (u, v)
        $sabotagedCertificate = [
            'u' => [-500, -600],
            'v' => [0, 200],
        ];

        // Verifier dual potential PR2 harus menolak sertifikat yang disabotase
        $this->assertFalse($verifier->verify(
            costMatrix: $costMatrix,
            assignment: $assignment,
            totalCost: $totalCost,
            certificate: $sabotagedCertificate
        ));
    }

    public function test_sabotase_2_satu_batasan_lp_dihapus_sertifikat_dual_lp_ditolak(): void
    {
        $verifier = app(LpCertificateVerifier::class);

        // Problem LP dengan batasan kualitas
        $lpProblem = [
            'c' => [-210, -150],
            'A' => [
                [1, 0],
                [0, 1],
                [1, 1],
                [-1, -1],
                [-30, 30], // Batasan rasio kadar
            ],
            'b' => [30000, 30000, 40000, -40000, 0],
        ];
        $x = ['20000', '20000'];
        $validCertificate = [
            'status' => 'OPTIMAL',
            'y' => ['0', '0', '0', '-180', '1'],
        ];

        // Sabotase: Hapus baris batasan rasio dari problem tapi klaim solusi yang sama
        $sabotagedProblem = [
            'c' => [-210, -150],
            'A' => [
                [1, 0],
                [0, 1],
                [1, 1],
                [-1, -1],
            ],
            'b' => [30000, 30000, 40000, -40000],
        ];

        $this->assertFalse($verifier->verifyOptimal(
            $sabotagedProblem,
            $x,
            $validCertificate
        ));
    }

    public function test_sabotase_3_timbangan_vs_mutasi_stockpile_dimanipulasi_tertangkap_audit(): void
    {
        $wbAction = app(RecordWeighbridgeTicketAction::class);
        $res = $wbAction->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-SAB-01',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000,
            gradeSpecs: ['metal_bps' => 180],
            idempotencyKey: 'sab:wb:001'
        );

        // Sabotase: Naikkan mutasi stockpile tanpa ada tiket timbang yang mendasarinya (phantom stock)
        DB::table('min_stockpile_movements')->insert([
            'id' => (string) Str::uuid(),
            'movement_uuid' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'stockpile_id' => $this->stockpile->id,
            'movement_type' => 'INFLOW_HAUL',
            'direction' => 'IN',
            'weight_kg' => 999999, // Manipulasi mutasi fiktif
            'reference_type' => 'MANUAL_TAMPER',
            'reference_id' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('min:audit');
        $this->assertSame(1, $exitCode);
    }

    public function test_sabotase_4_formula_atau_saldo_royalti_dimanipulasi_tertangkap_audit(): void
    {
        $wbAction = app(RecordWeighbridgeTicketAction::class);
        $resWb = $wbAction->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-SAB-01',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000,
            gradeSpecs: ['metal_bps' => 180],
            idempotencyKey: 'sab:wb:002'
        );

        $periodLocal = now()->setTimezone('Asia/Makassar')->format('Y-m');
        $calcAction = app(CalculateMonthlyRoyaltyAction::class);
        $calcAction->handle(
            siteId: $this->site->id,
            commodity: 'NICKEL',
            periodLocal: $periodLocal,
            priceUsdCentsPerTon: 12050,
            exchangeRateIdr1e6: 16250000000,
            tariffBps: 1350,
            idempotencyKey: 'sab:roy:001'
        );

        // Sabotase: Manipulasi angka royalti pada tabel fakta
        MiningRoyaltyReturn::query()->update(['calculated_royalty_idr' => 1000]); // Disunat dari 13 juta ke 1 ribu

        $exitCode = Artisan::call('min:audit');
        $this->assertSame(1, $exitCode);
    }

    public function test_sabotase_5_rantai_hash_tiket_timbang_dipalsukan_tertangkap_audit(): void
    {
        $wbAction = app(RecordWeighbridgeTicketAction::class);
        $res = $wbAction->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-SAB-01',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000,
            gradeSpecs: ['metal_bps' => 180],
            idempotencyKey: 'sab:wb:003'
        );

        // Sabotase: Palsukan ticket_hash di tabel database secara langsung
        DB::table('min_weighbridge_tickets')
            ->where('id', $res->ticket->id)
            ->update(['ticket_hash' => 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff']);

        $exitCode = Artisan::call('min:audit');
        $this->assertSame(1, $exitCode);
    }

    public function test_sabotase_6_tiket_timbang_diupdate_atau_didelete_ditolak_append_only(): void
    {
        $wbAction = app(RecordWeighbridgeTicketAction::class);
        $res = $wbAction->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-SAB-01',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000,
            gradeSpecs: ['metal_bps' => 180],
            idempotencyKey: 'sab:wb:004'
        );

        $ticket = WeighbridgeTicket::findOrFail($res->ticket->id);

        // a) Model menolak updating (PR5)
        $updatingRejected = false;
        try {
            $ticket->update(['net_weight_kg' => 99999]);
        } catch (\RuntimeException $e) {
            $updatingRejected = true;
            $this->assertStringContainsString('append-only immutable audit fact', $e->getMessage());
        }
        $this->assertTrue($updatingRejected);

        // b) Model menolak deleting (PR5)
        $deletingRejected = false;
        try {
            $ticket->delete();
        } catch (\RuntimeException $e) {
            $deletingRejected = true;
            $this->assertStringContainsString('append-only immutable audit fact', $e->getMessage());
        }
        $this->assertTrue($deletingRejected);

        // c) Bila manipulasi dilakukan lewat raw Query Builder melewati model:
        // Audit dua-sumber hash chain akan segera menangkap ketidakkonsistenan fakta
        DB::table('min_weighbridge_tickets')
            ->where('id', $res->ticket->id)
            ->update(['net_weight_kg' => 1000]);

        $exitCode = Artisan::call('min:audit');
        $this->assertSame(1, $exitCode);
    }
}
