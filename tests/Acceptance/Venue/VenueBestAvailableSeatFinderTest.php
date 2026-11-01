<?php

declare(strict_types=1);

namespace Tests\Acceptance\Venue;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Venue\Domain\Algorithms\BestAvailableSeatFinder;
use Modules\Venue\Domain\Algorithms\SeatAllocationResult;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\SeatClaim;
use Modules\Venue\Domain\Models\TicketTier;
use Tests\TestCase;

class VenueBestAvailableSeatFinderTest extends TestCase
{
    use RefreshDatabase;

    private EntertainmentEvent $event;

    private TicketTier $tier;

    protected function setUp(): void
    {
        parent::setUp();

        $venue = EntertainmentVenue::create([
            'venue_code' => 'THEATRE-JKT-01',
            'name' => 'Teater Jakarta Taman Ismail Marzuki',
            'venue_type' => 'THEATRE',
            'city' => 'Jakarta Pusat',
            'max_legal_capacity' => 1200,
            'min_age_requirement' => 12,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-OPERA-01',
            'venue_id' => $venue->id,
            'title' => 'Nusantara Opera Gala',
            'doors_open_at' => now()->addDays(15),
            'ticket_floor_price_idr' => 500000,
            'ticket_ceiling_price_idr' => 2000000,
            'current_ticket_price_idr' => 1200000,
        ]);

        $this->tier = TicketTier::create([
            'event_id' => $this->event->id,
            'zone_id' => null,
            'name' => 'Stalls Center',
            'tier_code' => 'STALLS-CTR',
            'tier_type' => 'NUMBERED_SEATS',
            'price_idr' => 1200000,
            'quota' => 30,
            'is_numbered_seating' => true,
            'allows_reentry' => true,
        ]);
    }

    public function test_mengalokasikan_kursi_berdampingan_pada_baris_terdepan_secara_deterministik(): void
    {
        // Buat Baris A (1..10) dan Baris B (1..10)
        foreach (['A', 'B'] as $row) {
            for ($s = 1; $s <= 10; $s++) {
                SeatClaim::create([
                    'event_id' => $this->event->id,
                    'tier_id' => $this->tier->id,
                    'section_code' => 'STALLS',
                    'row_label' => $row,
                    'seat_number' => $s,
                    'status' => 'AVAILABLE',
                ]);
            }
        }

        $finder = app(BestAvailableSeatFinder::class);

        // Minta 3 kursi berdampingan
        $result = $finder->findBestAvailable(
            eventId: $this->event->id,
            tierId: $this->tier->id,
            quantity: 3
        );

        $this->assertInstanceOf(SeatAllocationResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertCount(3, $result->seats);

        // Harus memilih Baris A kursi 1, 2, 3 (atau window pertama yang tidak meninggalkan orphan)
        $allocatedSeatNumbers = array_map(fn ($s) => $s['seat_number'], $result->seats);
        $allocatedRows = array_unique(array_map(fn ($s) => $s['row_label'], $result->seats));

        $this->assertSame(['A'], array_values($allocatedRows));
        $this->assertSame([1, 2, 3], $allocatedSeatNumbers);
    }

    public function test_anti_orphan_menolak_alokasi_yang_menyisakan_tepat_satu_kursi_kosong_terisolasi(): void
    {
        // Baris A memiliki 6 kursi: 1, 2, 3, 4, 5, 6
        // Kursi 1..6 AVAILABLE
        for ($s = 1; $s <= 6; $s++) {
            SeatClaim::create([
                'event_id' => $this->event->id,
                'tier_id' => $this->tier->id,
                'section_code' => 'STALLS',
                'row_label' => 'A',
                'seat_number' => $s,
                'status' => 'AVAILABLE',
            ]);
        }

        // Misalkan kursi 1 sudah BOOKED
        SeatClaim::where('event_id', $this->event->id)->where('seat_number', 1)->update(['status' => 'BOOKED']);

        // Sisa kursi AVAILABLE: 2, 3, 4, 5, 6
        // Jika pelanggan meminta 3 kursi:
        // Pilihan window 1: [3, 4, 5] -> Menyisakan kursi 2 sendirian di sebelah kiri (ORPHAN SEAT)!
        // Aturan Anti-Orphan: window [3, 4, 5] DITOLAK karena kursi 2 terisolasi.
        // Pilihan window yang valid: [2, 3, 4] -> Sisa [5, 6] (2 kursi kosong, bukan orphan 1 kursi).
        $finder = app(BestAvailableSeatFinder::class);

        $result = $finder->findBestAvailable(
            eventId: $this->event->id,
            tierId: $this->tier->id,
            quantity: 3
        );

        $this->assertTrue($result->isSuccess());
        $allocatedNumbers = array_map(fn ($s) => $s['seat_number'], $result->seats);
        $this->assertSame([2, 3, 4], $allocatedNumbers, 'Harus memilih [2, 3, 4] agar kursi 2 tidak menjadi orphan');
    }

    public function test_berpindah_ke_baris_berikutnya_jika_baris_terdepan_tidak_cukup_kursi_berdampingan(): void
    {
        // Baris A hanya tersisa kursi 1 dan 5 (tidak berdampingan 3 kursi)
        SeatClaim::create([
            'event_id' => $this->event->id,
            'tier_id' => $this->tier->id,
            'section_code' => 'STALLS',
            'row_label' => 'A',
            'seat_number' => 1,
            'status' => 'AVAILABLE',
        ]);
        SeatClaim::create([
            'event_id' => $this->event->id,
            'tier_id' => $this->tier->id,
            'section_code' => 'STALLS',
            'row_label' => 'A',
            'seat_number' => 2,
            'status' => 'BOOKED',
        ]);

        // Baris B memiliki kursi 1, 2, 3, 4, 5 AVAILABLE
        for ($s = 1; $s <= 5; $s++) {
            SeatClaim::create([
                'event_id' => $this->event->id,
                'tier_id' => $this->tier->id,
                'section_code' => 'STALLS',
                'row_label' => 'B',
                'seat_number' => $s,
                'status' => 'AVAILABLE',
            ]);
        }

        $finder = app(BestAvailableSeatFinder::class);

        $result = $finder->findBestAvailable(
            eventId: $this->event->id,
            tierId: $this->tier->id,
            quantity: 3
        );

        $this->assertTrue($result->isSuccess());
        $allocatedRows = array_unique(array_map(fn ($s) => $s['row_label'], $result->seats));
        $this->assertSame(['B'], array_values($allocatedRows));
    }

    public function test_mengembalikan_status_gagal_jika_tidak_ada_blok_kursi_berdampingan_yang_memenuhi_syarat(): void
    {
        // Hanya ada 2 kursi per baris, pelanggan meminta 4 kursi
        SeatClaim::create([
            'event_id' => $this->event->id,
            'tier_id' => $this->tier->id,
            'section_code' => 'STALLS',
            'row_label' => 'A',
            'seat_number' => 1,
            'status' => 'AVAILABLE',
        ]);
        SeatClaim::create([
            'event_id' => $this->event->id,
            'tier_id' => $this->tier->id,
            'section_code' => 'STALLS',
            'row_label' => 'A',
            'seat_number' => 2,
            'status' => 'AVAILABLE',
        ]);

        $finder = app(BestAvailableSeatFinder::class);

        $result = $finder->findBestAvailable(
            eventId: $this->event->id,
            tierId: $this->tier->id,
            quantity: 4
        );

        $this->assertFalse($result->isSuccess());
        $this->assertEmpty($result->seats);
    }

    public function test_pemesanan_satu_kursi_bebas_dari_aturan_anti_orphan(): void
    {
        // Sisa kursi single di antara dua booked
        SeatClaim::create([
            'event_id' => $this->event->id,
            'tier_id' => $this->tier->id,
            'section_code' => 'STALLS',
            'row_label' => 'A',
            'seat_number' => 1,
            'status' => 'BOOKED',
        ]);
        SeatClaim::create([
            'event_id' => $this->event->id,
            'tier_id' => $this->tier->id,
            'section_code' => 'STALLS',
            'row_label' => 'A',
            'seat_number' => 2,
            'status' => 'AVAILABLE', // Kursi tunggal
        ]);
        SeatClaim::create([
            'event_id' => $this->event->id,
            'tier_id' => $this->tier->id,
            'section_code' => 'STALLS',
            'row_label' => 'A',
            'seat_number' => 3,
            'status' => 'BOOKED',
        ]);

        $finder = app(BestAvailableSeatFinder::class);

        // Pelanggan solo (quantity = 1) diizinkan mengisi kursi tunggal tersebut
        $result = $finder->findBestAvailable(
            eventId: $this->event->id,
            tierId: $this->tier->id,
            quantity: 1
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame(2, $result->seats[0]['seat_number']);
    }

    public function test_hasil_alokasi_menyertakan_sertifikat_validitas_alokasi_pr2(): void
    {
        for ($s = 1; $s <= 5; $s++) {
            SeatClaim::create([
                'event_id' => $this->event->id,
                'tier_id' => $this->tier->id,
                'section_code' => 'STALLS',
                'row_label' => 'A',
                'seat_number' => $s,
                'status' => 'AVAILABLE',
            ]);
        }

        $finder = app(BestAvailableSeatFinder::class);

        $result = $finder->findBestAvailable(
            eventId: $this->event->id,
            tierId: $this->tier->id,
            quantity: 2
        );

        $this->assertNotNull($result->certificate);
        $this->assertTrue($result->certificate->isValid());
    }

    public function test_verifier_sertifikat_alokasi_menolak_sertifikat_yang_dimanipulasi(): void
    {
        for ($s = 1; $s <= 5; $s++) {
            SeatClaim::create([
                'event_id' => $this->event->id,
                'tier_id' => $this->tier->id,
                'section_code' => 'STALLS',
                'row_label' => 'A',
                'seat_number' => $s,
                'status' => 'AVAILABLE',
            ]);
        }

        $finder = app(BestAvailableSeatFinder::class);
        $result = $finder->findBestAvailable($this->event->id, $this->tier->id, 2);

        // Manipulasi kursi alokasi secara ilegal
        $tamperedResult = new SeatAllocationResult(
            seats: [['id' => 999, 'row_label' => 'Z', 'seat_number' => 99]],
            certificate: $result->certificate
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Sertifikat alokasi kursi tidak valid');
        $result->certificate->verify($tamperedResult);
    }

    public function test_benchmark_alokasi_best_available_pada_1000_kursi_kurang_dari_0_2_detik(): void
    {
        // Setup 50 baris x 20 kursi = 1000 kursi
        $rows = range('A', 'Z');
        $seatData = [];
        $now = now();
        foreach (range(1, 50) as $rIdx) {
            $rowLabel = 'R'.$rIdx;
            for ($s = 1; $s <= 20; $s++) {
                $seatData[] = [
                    'event_id' => $this->event->id,
                    'tier_id' => $this->tier->id,
                    'section_code' => 'MAIN',
                    'row_label' => $rowLabel,
                    'seat_number' => $s,
                    'status' => 'AVAILABLE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        SeatClaim::insert($seatData);

        $finder = app(BestAvailableSeatFinder::class);

        $start = microtime(true);
        $result = $finder->findBestAvailable($this->event->id, $this->tier->id, 4);
        $elapsed = microtime(true) - $start;

        $this->assertTrue($result->isSuccess());
        $this->assertLessThan(0.2, $elapsed);
    }
}
