<?php

declare(strict_types=1);

namespace Tests\Acceptance\Venue;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Venue\Application\Actions\HoldGeneralAdmissionQuotaAction;
use Modules\Venue\Application\Actions\HoldNumberedSeatsAction;
use Modules\Venue\Application\Actions\ReleaseExpiredHoldsAction;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\InventoryCounter;
use Modules\Venue\Domain\Models\SeatClaim;
use Modules\Venue\Domain\Models\TicketTier;
use Tests\TestCase;

class VenueInventoryAndHoldTest extends TestCase
{
    use RefreshDatabase;

    private EntertainmentVenue $venue;

    private EntertainmentEvent $event;

    private EntertainmentZone $zone;

    private TicketTier $gaTier;

    private TicketTier $seatedTier;

    protected function setUp(): void
    {
        parent::setUp();

        $users = [];
        for ($i = 101; $i <= 110; $i++) {
            $users[] = ['id' => $i, 'name' => "Buyer {$i}", 'email' => "buyer{$i}@test.com", 'password' => 'secret'];
        }
        DB::table('users')->insertOrIgnore($users);

        $this->venue = EntertainmentVenue::create([
            'venue_code' => 'BC-BALI-002',
            'name' => 'Finns VIP Beach Club',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Canggu',
            'max_legal_capacity' => 2000,
            'min_age_requirement' => 21,
        ]);

        $this->zone = EntertainmentZone::create([
            'zone_code' => 'ZONE-BEACH-01',
            'venue_id' => $this->venue->id,
            'name' => 'Beachfront Lawn',
            'capacity_limit' => 800,
            'current_occupancy' => 0,
            'crowd_lockdown_active' => false,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-FINNS-01',
            'venue_id' => $this->venue->id,
            'title' => 'Finns Tropical Fest',
            'doors_open_at' => now()->addDays(5),
            'ticket_floor_price_idr' => 200000,
            'ticket_ceiling_price_idr' => 1000000,
            'current_ticket_price_idr' => 500000,
        ]);

        $this->gaTier = TicketTier::create([
            'event_id' => $this->event->id,
            'zone_id' => $this->zone->id,
            'name' => 'General Admission Tier 1',
            'tier_code' => 'GA-T1',
            'tier_type' => 'GENERAL_ADMISSION',
            'price_idr' => 500000,
            'quota' => 100,
            'is_numbered_seating' => false,
            'allows_reentry' => false,
        ]);

        InventoryCounter::create([
            'event_id' => $this->event->id,
            'tier_id' => $this->gaTier->id,
            'total_quota' => 100,
            'held_count' => 0,
            'sold_count' => 0,
        ]);

        $this->seatedTier = TicketTier::create([
            'event_id' => $this->event->id,
            'zone_id' => null,
            'name' => 'VIP Pavilion Seated',
            'tier_code' => 'VIP-PAV',
            'tier_type' => 'NUMBERED_SEATS',
            'price_idr' => 1500000,
            'quota' => 10,
            'is_numbered_seating' => true,
            'allows_reentry' => true,
        ]);

        for ($s = 1; $s <= 10; $s++) {
            SeatClaim::create([
                'event_id' => $this->event->id,
                'tier_id' => $this->seatedTier->id,
                'section_code' => 'PAVILION',
                'row_label' => 'A',
                'seat_number' => $s,
                'status' => 'AVAILABLE',
            ]);
        }
    }

    public function test_hold_kuota_ga_mengurangi_stok_tersedia_dan_menetapkan_durasi_10_menit(): void
    {
        $action = app(HoldGeneralAdmissionQuotaAction::class);

        $result = $action->handle(
            eventId: $this->event->id,
            tierId: $this->gaTier->id,
            quantity: 4,
            buyerUserId: 101,
            idempotencyKey: 'ven:hold:ga:101:1'
        );

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['hold_token']);
        $this->assertSame(4, $result['quantity']);

        $counter = InventoryCounter::where('event_id', $this->event->id)->where('tier_id', $this->gaTier->id)->firstOrFail();
        $this->assertSame(4, $counter->held_count);
        $this->assertSame(0, $counter->sold_count);
    }

    public function test_hold_kuota_menolak_permintaan_melebihi_sisa_kuota(): void
    {
        $action = app(HoldGeneralAdmissionQuotaAction::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Kuota tiket tidak mencukupi');

        $action->handle(
            eventId: $this->event->id,
            tierId: $this->gaTier->id,
            quantity: 101, // Melebihi total quota 100
            buyerUserId: 102,
            idempotencyKey: 'ven:hold:ga:102:1'
        );
    }

    public function test_hold_kursi_bernomor_mengunci_kursi_spesifik_dan_mencegah_double_hold(): void
    {
        $action = app(HoldNumberedSeatsAction::class);

        $result = $action->handle(
            eventId: $this->event->id,
            tierId: $this->seatedTier->id,
            seatIds: [1, 2],
            buyerUserId: 103,
            idempotencyKey: 'ven:hold:seats:103:1'
        );

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['seats']);

        $seat1 = SeatClaim::findOrFail(1);
        $this->assertSame('HELD', $seat1->status);
        $this->assertSame(103, $seat1->buyer_user_id);

        // Percobaan hold kursi yang sama oleh user lain harus ditolak
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Beberapa kursi yang dipilih tidak tersedia');

        $action->handle(
            eventId: $this->event->id,
            tierId: $this->seatedTier->id,
            seatIds: [2, 3], // Kursi 2 sudah HELD
            buyerUserId: 104,
            idempotencyKey: 'ven:hold:seats:104:1'
        );
    }

    public function test_hold_kedaluwarsa_dilepas_oleh_clock_setelah_10_menit(): void
    {
        $fakeClock = new class implements ClockInterface
        {
            public CarbonImmutable $current;

            public function __construct()
            {
                $this->current = CarbonImmutable::parse('2026-10-30 10:00:00');
            }

            public function now(): CarbonImmutable
            {
                return $this->current;
            }
        };
        $this->app->instance(ClockInterface::class, $fakeClock);

        $action = app(HoldGeneralAdmissionQuotaAction::class);
        $hold = $action->handle(
            eventId: $this->event->id,
            tierId: $this->gaTier->id,
            quantity: 5,
            buyerUserId: 105,
            idempotencyKey: 'ven:hold:ga:105:1'
        );

        // Majukan jam sebanyak 9 menit 59 detik: hold masih aktif
        $fakeClock->current = $fakeClock->current->addMinutes(9)->addSeconds(59);
        $releaseAction = app(ReleaseExpiredHoldsAction::class);
        $releasedCount = $releaseAction->handle();
        $this->assertSame(0, $releasedCount);

        // Majukan jam melebihi 10 menit (10 menit 1 detik): hold kedaluwarsa dilepas
        $fakeClock->current = $fakeClock->current->addSeconds(2);
        $releasedCount = $releaseAction->handle();
        $this->assertGreaterThanOrEqual(1, $releasedCount);

        $counter = InventoryCounter::where('event_id', $this->event->id)->where('tier_id', $this->gaTier->id)->firstOrFail();
        $this->assertSame(0, $counter->held_count, 'Held count harus kembali 0 setelah kedaluwarsa dilepas');
    }

    public function test_batas_beli_anti_calo_maksimal_6_tiket_per_akun_per_event(): void
    {
        $action = app(HoldGeneralAdmissionQuotaAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Batas pembelian maksimum adalah 6 tiket per akun');

        $action->handle(
            eventId: $this->event->id,
            tierId: $this->gaTier->id,
            quantity: 7, // Melebihi batas beli 6 (V-3)
            buyerUserId: 106,
            idempotencyKey: 'ven:hold:ga:106:1'
        );
    }

    public function test_hold_idempoten_dengan_idempotency_key_sama_mengembalikan_hold_token_identik(): void
    {
        $action = app(HoldGeneralAdmissionQuotaAction::class);

        $res1 = $action->handle(
            eventId: $this->event->id,
            tierId: $this->gaTier->id,
            quantity: 2,
            buyerUserId: 107,
            idempotencyKey: 'ven:hold:ga:107:idemp'
        );

        $res2 = $action->handle(
            eventId: $this->event->id,
            tierId: $this->gaTier->id,
            quantity: 2,
            buyerUserId: 107,
            idempotencyKey: 'ven:hold:ga:107:idemp'
        );

        $this->assertSame($res1['hold_token'], $res2['hold_token']);

        $counter = InventoryCounter::where('event_id', $this->event->id)->where('tier_id', $this->gaTier->id)->firstOrFail();
        $this->assertSame(2, $counter->held_count, 'Held count tidak boleh berganda karena idempotensi');
    }

    public function test_unique_constraint_db_menolak_duplikasi_seat_claim_pada_event_sama(): void
    {
        $this->expectException(QueryException::class);

        SeatClaim::create([
            'event_id' => $this->event->id,
            'tier_id' => $this->seatedTier->id,
            'section_code' => 'PAVILION',
            'row_label' => 'A',
            'seat_number' => 1, // Sudah ada di setUp
            'status' => 'AVAILABLE',
        ]);
    }

    public function test_unique_constraint_db_menolak_duplikasi_inventory_counter_event_dan_tier(): void
    {
        $this->expectException(QueryException::class);

        InventoryCounter::create([
            'event_id' => $this->event->id,
            'tier_id' => $this->gaTier->id, // Sudah ada di setUp
            'total_quota' => 50,
            'held_count' => 0,
            'sold_count' => 0,
        ]);
    }
}
