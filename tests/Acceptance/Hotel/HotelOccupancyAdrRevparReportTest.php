<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Actions\CheckInGuestAction;
use Modules\Hotel\Application\Actions\ExecuteHotelNightAuditAction;
use Modules\Hotel\Application\Actions\ReserveRoomAction;
use Modules\Hotel\Application\DTOs\ReserveRoomDTO;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\Application\Services\HotelReportService;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Tests\TestCase;

class HotelOccupancyAdrRevparReportTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private User $guest;

    private HotelRoomType $roomType;

    private HotelRatePlan $ratePlan;

    private HotelBusinessDateService $businessDateService;

    private HotelReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->guest = User::factory()->create(['role' => 'guest']);
        $this->roomType = HotelRoomType::where('property_id', $this->property->id)->firstOrFail();
        $this->ratePlan = HotelRatePlan::where('property_id', $this->property->id)->firstOrFail();

        // Bersihkan data seed default agar pengujian metrik presisi
        HotelReservation::where('property_id', $this->property->id)->delete();
        HotelRoom::where('property_id', $this->property->id)->delete();

        $this->businessDateService = app(HotelBusinessDateService::class);
        $this->businessDateService->initializeBusinessDate($this->property->id, '2026-11-01');
        $this->reportService = app(HotelReportService::class);
    }

    private function createOccupiedRoom(string $roomNumber, int $rateIdr, string $status = 'available'): HotelRoom
    {
        $room = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => $roomNumber,
            'room_type' => 'DELUXE',
            'base_rate_idr' => $rateIdr,
            'status' => $status,
            'energy_setback_active' => true,
        ]);

        if ($status === 'available') {
            $reserveAction = app(ReserveRoomAction::class);
            $res = $reserveAction->execute(new ReserveRoomDTO(
                propertyId: $this->property->id,
                roomTypeId: $this->roomType->id,
                ratePlanId: $this->ratePlan->id,
                guestUserId: (string) $this->guest->id,
                checkInDate: '2026-11-01',
                checkOutDate: '2026-11-03',
                idempotencyKey: 'res-occ-'.Str::random(6),
            ));
            $res->update(['daily_rate_idr' => $rateIdr]);

            $checkInAction = app(CheckInGuestAction::class);
            $checkInAction->checkIn(
                reservationId: $res->id,
                assignedRoomId: $room->id,
                idType: 'KTP',
                idNumber: '317101234567'.Str::random(4),
                fullName: 'Tamu '.$roomNumber,
            );
        }

        return $room;
    }

    public function test_perhitungan_tingkat_okupansi_harian_akurat_berdasarkan_kamar_terjual_dan_tersedia(): void
    {
        // Bersihkan kamar default agar metrik presisi
        HotelRoom::where('property_id', $this->property->id)->delete();

        // 4 kamar occupied, 1 kamar out of order, 1 kamar available kosong
        // Total kamar: 6. Out of order: 1. Tersedia dijual: 5. Terisi: 4.
        // Okupansi = 4 / 5 = 80.00% = 8000 bps
        $this->createOccupiedRoom('RM-101', 1000000);
        $this->createOccupiedRoom('RM-102', 1000000);
        $this->createOccupiedRoom('RM-103', 1000000);
        $this->createOccupiedRoom('RM-104', 1000000);

        HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'RM-OOO',
            'room_type' => 'DELUXE',
            'base_rate_idr' => 1000000,
            'status' => 'out_of_order',
            'energy_setback_active' => false,
        ]);

        HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'RM-VACANT',
            'room_type' => 'DELUXE',
            'base_rate_idr' => 1000000,
            'status' => 'available',
            'energy_setback_active' => true,
        ]);

        $auditAction = app(ExecuteHotelNightAuditAction::class);
        $snapshot = $auditAction->execute($this->property->id, '2026-11-01');

        $this->assertEquals(6, $snapshot->total_rooms);
        $this->assertEquals(4, $snapshot->occupied_rooms);
        $this->assertEquals(1, $snapshot->out_of_order_rooms);
        $this->assertEquals(8000, $snapshot->occupancy_rate_bps); // 80.00%

        $report = $this->reportService->getDailyReport($this->property->id, '2026-11-01');
        $this->assertEquals(80.0, $report['occupancy_rate_percent']);
        $this->assertEquals(5, $report['available_rooms']);
    }

    public function test_perhitungan_average_daily_rate_adr_sesuai_dengan_realisasi_pendapatan_kamar_dibagi_kamar_terjual(): void
    {
        HotelRoom::where('property_id', $this->property->id)->delete();

        // Kamar 1: 1.500.000 IDR
        // Kamar 2: 2.500.000 IDR
        // Total Pendapatan Kamar = 4.000.000 IDR
        // Kamar Terjual (Occupied) = 2
        // ADR = 4.000.000 / 2 = 2.000.000 IDR
        $this->createOccupiedRoom('RM-ADR-1', 1500000);
        $this->createOccupiedRoom('RM-ADR-2', 2500000);

        $auditAction = app(ExecuteHotelNightAuditAction::class);
        $snapshot = $auditAction->execute($this->property->id, '2026-11-01');

        $this->assertEquals(4000000, $snapshot->total_room_revenue_idr);
        $this->assertEquals(2, $snapshot->occupied_rooms);
        $this->assertEquals(2000000, $snapshot->adr_idr);

        $report = $this->reportService->getDailyReport($this->property->id, '2026-11-01');
        $this->assertEquals(2000000, $report['adr_idr']);
    }

    public function test_perhitungan_revpar_sesuai_dengan_total_pendapatan_kamar_dibagi_total_kamar_tersedia(): void
    {
        HotelRoom::where('property_id', $this->property->id)->delete();

        // 2 kamar terisi @ 1.000.000 = 2.000.000 IDR total kamar
        // 2 kamar kosong available
        // Total kamar tersedia dijual = 4
        // RevPAR = 2.000.000 / 4 = 500.000 IDR
        $this->createOccupiedRoom('RM-REV-1', 1000000);
        $this->createOccupiedRoom('RM-REV-2', 1000000);

        HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'RM-VAC-1',
            'room_type' => 'DELUXE',
            'base_rate_idr' => 1000000,
            'status' => 'available',
            'energy_setback_active' => true,
        ]);
        HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'RM-VAC-2',
            'room_type' => 'DELUXE',
            'base_rate_idr' => 1000000,
            'status' => 'available',
            'energy_setback_active' => true,
        ]);

        $auditAction = app(ExecuteHotelNightAuditAction::class);
        $snapshot = $auditAction->execute($this->property->id, '2026-11-01');

        $this->assertEquals(4, $snapshot->total_rooms);
        $this->assertEquals(2, $snapshot->occupied_rooms);
        $this->assertEquals(2000000, $snapshot->total_room_revenue_idr);
        $this->assertEquals(500000, $snapshot->revpar_idr);

        // Verifikasi hubungan teoritis: RevPAR = ADR * (Occupancy / 100)
        // ADR = 1.000.000, Occupancy = 50.00% -> 1.000.000 * 0.50 = 500.000
        $this->assertEquals($snapshot->adr_idr * ($snapshot->occupancy_rate_bps / 10000), $snapshot->revpar_idr);
    }

    public function test_angka_laporan_pendapatan_bersumber_dari_agregasi_ledger_bukan_konstanta_statis(): void
    {
        HotelRoom::where('property_id', $this->property->id)->delete();

        // Buat 1 kamar dengan nominal unik 1.875.000
        $this->createOccupiedRoom('RM-DYN-1', 1875000);

        $auditAction = app(ExecuteHotelNightAuditAction::class);
        $snapshot = $auditAction->execute($this->property->id, '2026-11-01');

        $report = $this->reportService->getDailyReport($this->property->id, '2026-11-01');

        // Pastikan pendapatan dinamis cocok dengan angka unik
        $this->assertEquals(1875000, $report['total_room_revenue_idr']);
        $this->assertEquals(1875000, $report['ledger_room_revenue_idr']);
        $this->assertTrue($report['is_reconciled']);
    }
}
