<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mining;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Core\Contracts\ClockInterface;
use Modules\Mining\Application\Actions\RecordWeighbridgeTicketAction;
use Modules\Mining\Domain\Events\MiningWeighbridgeRecorded;
use Modules\Mining\Domain\Models\MiningArea;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Mining\Domain\Models\WeighbridgeTicket;
use Modules\Mining\Domain\Services\MiningWeighbridgeChainService;
use Tests\TestCase;

class MiningWeighbridgeAndTicketTest extends TestCase
{
    use RefreshDatabase;

    protected MiningSite $site;

    protected MiningPit $pit;

    protected MiningArea $area;

    protected MiningEquipment $truck;

    protected MiningStockpile $stockpile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-SUL-01',
            'name' => 'Pomalaa Nickel Site',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar',
            'location' => 'Kolaka, Sulawesi Tenggara',
            'status' => 'ACTIVE',
        ]);

        $this->pit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_code' => 'PIT-NORTH-01',
            'name' => 'Pit North Block A',
            'target_production_ton' => 100000,
            'status' => 'ACTIVE',
        ]);

        $this->area = MiningArea::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_id' => $this->pit->id,
            'area_code' => 'AREA-EXC-01',
            'name' => 'Excavation Sector 1',
            'status' => 'ACTIVE',
        ]);

        $this->truck = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-HD-101',
            'type' => 'HAUL_TRUCK',
            'model' => 'Komatsu HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
            'engine_hours' => 1500,
        ]);

        $this->stockpile = MiningStockpile::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'stockpile_code' => 'STK-ROM-01',
            'name' => 'Run of Mine Stockpile 1',
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
            'permit_number' => 'PTW-2026-001',
            'permit_type' => 'HAULAGE_OPERATION',
            'worker_party_id' => 'DVR-101',
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            'approval_status' => 'APPROVED',
        ]);
    }

    public function test_penimbangan_berhasil_menghitung_neto_persis_dan_mencatat_tiket(): void
    {
        $action = app(RecordWeighbridgeTicketAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-101',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 80000,
            tareWeightKg: 35000,
            gradeSpecs: ['metal_bps' => 180],
            idempotencyKey: 'min:wb:rec:101'
        );

        $this->assertSame(45000, $res->ticket->net_weight_kg);
        $this->assertFalse($res->ticket->is_overload);
        $this->assertNotEmpty($res->ticket->ticket_hash);
        $this->assertDatabaseHas('min_weighbridge_tickets', [
            'id' => $res->ticket->id,
            'net_weight_kg' => 45000,
        ]);
    }

    public function test_tara_di_luar_toleransi_ditolak(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/tara.*toleransi/i');

        $action = app(RecordWeighbridgeTicketAction::class);

        // Registered tare is 35,000 kg. 100 bps tolerance = ±350 kg (range 34,650 to 35,350 kg).
        // Actual tare 36,000 kg exceeds tolerance!
        $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-101',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 36000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:rec:102'
        );
    }

    public function test_muatan_melebihi_kapasitas_ditandai_overload(): void
    {
        $action = app(RecordWeighbridgeTicketAction::class);

        // Capacity is 50,000 kg. Gross 90,000 - Tare 35,000 = 55,000 kg net (> 50,000 kg).
        $res = $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-101',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 90000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:rec:103'
        );

        $this->assertTrue($res->ticket->is_overload);
        $this->assertSame(55000, $res->ticket->net_weight_kg);
    }

    public function test_penimbangan_truk_sama_dalam_durasi_kurang_dari_600_detik_ditolak_sebagai_duplikat(): void
    {
        $action = app(RecordWeighbridgeTicketAction::class);

        $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-101',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 80000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:rec:104a'
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/duplikat.*siklus|min_cycle/i');

        // Second weighing attempt by same truck within < 600s must be rejected
        $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-101',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 80000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:rec:104b'
        );
    }

    public function test_hash_chain_tiket_timbang_mengikat_tiket_sebelumnya_secara_kriptografis(): void
    {
        $clock = app(ClockInterface::class);
        $action = app(RecordWeighbridgeTicketAction::class);

        $res1 = $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-101',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 80000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:rec:105a'
        );

        // Advance simulated time past 600s
        $clock->travel(650);

        $truck2 = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-HD-102',
            'type' => 'HAUL_TRUCK',
            'model' => 'Komatsu HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
        ]);

        $res2 = $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $truck2->id,
            driverPartyId: 'DVR-101',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 82000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:rec:105b'
        );

        $this->assertNull($res1->ticket->prev_hash);
        $this->assertSame($res1->ticket->ticket_hash, $res2->ticket->prev_hash);

        // Verify chain integrity via chain service
        $chainService = app(MiningWeighbridgeChainService::class);
        $this->assertTrue($chainService->verifySiteChain($this->site->id));
    }

    public function test_tiket_timbang_dan_mutasi_stockpile_dibuat_secara_atomik(): void
    {
        $action = app(RecordWeighbridgeTicketAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-101',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 80000,
            tareWeightKg: 35000,
            gradeSpecs: ['metal_bps' => 195],
            idempotencyKey: 'min:wb:rec:106'
        );

        $this->assertDatabaseHas('min_stockpile_movements', [
            'stockpile_id' => $this->stockpile->id,
            'direction' => 'IN',
            'weight_kg' => 45000,
            'reference_id' => $res->ticket->id,
        ]);

        $stockpileUpdated = MiningStockpile::findOrFail($this->stockpile->id);
        $this->assertSame(45000, $stockpileUpdated->total_kg);
        $this->assertSame(45000 * 195, $stockpileUpdated->sum_kg_metal_bps);
    }

    public function test_penimbangan_oleh_operator_tanpa_izin_kerja_aktif_ditolak(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/izin kerja|permit/i');

        $action = app(RecordWeighbridgeTicketAction::class);

        // Non-existent or expired driver party
        $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'UNKNOWN-DRIVER-999',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 80000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:rec:107'
        );
    }

    public function test_penimbangan_pada_area_tambang_yang_dihentikan_ditolak(): void
    {
        $this->area->update([
            'status' => 'STOPPED',
            'stop_reason' => 'Geotech radar slope failure hazard',
            'stopped_at' => now(),
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/area.*dihentikan|stopped/i');

        $action = app(RecordWeighbridgeTicketAction::class);

        $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-101',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 80000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:rec:108'
        );
    }

    public function test_penimbangan_tanpa_idempotency_key_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $action = app(RecordWeighbridgeTicketAction::class);

        $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-101',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 80000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: ''
        );
    }

    public function test_model_weighbridge_ticket_menolak_update_fakta_append_only(): void
    {
        $action = app(RecordWeighbridgeTicketAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-101',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 80000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:rec:110'
        );

        $ticket = WeighbridgeTicket::findOrFail($res->ticket->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/append-only|immutable/i');

        $ticket->net_weight_kg = 20000;
        $ticket->save();
    }

    public function test_model_weighbridge_ticket_menolak_delete_fakta_append_only(): void
    {
        $action = app(RecordWeighbridgeTicketAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-101',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 80000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:rec:111'
        );

        $ticket = WeighbridgeTicket::findOrFail($res->ticket->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/append-only|immutable/i');

        $ticket->delete();
    }

    public function test_penimbangan_menerbitkan_domain_event_weighbridge_recorded(): void
    {
        Event::fake([MiningWeighbridgeRecorded::class]);

        $action = app(RecordWeighbridgeTicketAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-101',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 80000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:rec:112'
        );

        Event::assertDispatched(MiningWeighbridgeRecorded::class, function ($event) use ($res) {
            return $event->ticketId === $res->ticket->id && $event->netWeightKg === 45000;
        });
    }
}
