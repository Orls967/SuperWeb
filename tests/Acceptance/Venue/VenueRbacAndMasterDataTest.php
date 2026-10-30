<?php

declare(strict_types=1);

namespace Tests\Acceptance\Venue;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Core\Domain\Models\Role;
use Modules\Venue\Database\Seeders\VenueRbacSeeder;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\TicketTier;
use Modules\Venue\Ledger\VenueAccounts;
use Tests\TestCase;

class VenueRbacAndMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_rbac_venue_mendaftarkan_role_venue_secara_idempoten(): void
    {
        $this->seed(VenueRbacSeeder::class);
        $this->seed(VenueRbacSeeder::class); // Uji idempotensi seeder U-5, Z8

        $this->assertTrue(Role::where('name', 'venue_manager')->exists());
        $this->assertTrue(Role::where('name', 'venue_staff')->exists());
        $this->assertTrue(Role::where('name', 'artist_relations')->exists());
    }

    public function test_role_venue_staff_memiliki_izin_pemindaian_pintu_turnstile(): void
    {
        $this->seed(VenueRbacSeeder::class);

        $staffRole = Role::where('name', 'venue_staff')->firstOrFail();
        $permissions = $staffRole->permissions->pluck('name')->all();

        $this->assertContains('venue.gate.scan', $permissions);
        $this->assertNotContains('venue.event.create', $permissions);
    }

    public function test_role_venue_manager_memiliki_izin_penuh_operasional_dan_tutup_event(): void
    {
        $this->seed(VenueRbacSeeder::class);

        $managerRole = Role::where('name', 'venue_manager')->firstOrFail();
        $permissions = $managerRole->permissions->pluck('name')->all();

        $this->assertContains('venue.event.create', $permissions);
        $this->assertContains('venue.event.close', $permissions);
        $this->assertContains('venue.table.manage', $permissions);
    }

    public function test_akun_kontrol_unearned_revenue_terdaftar_dengan_allow_negative_false(): void
    {
        $definitions = VenueAccounts::definitions();
        $accountMap = [];
        foreach ($definitions as $def) {
            $accountMap[$def['code']] = $def;
        }

        $this->assertArrayHasKey('ven:ticket_unearned:IDR', $accountMap);
        $this->assertSame(AccountKind::LIABILITY, $accountMap['ven:ticket_unearned:IDR']['kind']);
        $this->assertFalse($accountMap['ven:ticket_unearned:IDR']['allow_negative'], 'Akun kewajiban unearned tiket dilarang negatif (U-3)');
    }

    public function test_akun_escrow_vip_dan_clearing_terdaftar_dengan_jenis_yang_sesuai(): void
    {
        $definitions = VenueAccounts::definitions();
        $accountMap = [];
        foreach ($definitions as $def) {
            $accountMap[$def['code']] = $def;
        }

        $this->assertArrayHasKey('ven:vip_escrow:IDR', $accountMap);
        $this->assertSame(AccountKind::ESCROW, $accountMap['ven:vip_escrow:IDR']['kind']);

        $this->assertArrayHasKey('ven:ticket_clearing:IDR', $accountMap);
        $this->assertSame(AccountKind::CLEARING, $accountMap['ven:ticket_clearing:IDR']['kind']);
    }

    public function test_master_data_venue_zona_dan_tier_tiket_dapat_dibuat_dengan_valid(): void
    {
        $venue = EntertainmentVenue::create([
            'venue_code' => 'BC-BALI-001',
            'name' => 'Savaya Beach Club Uluwatu',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Badung',
            'max_legal_capacity' => 3000,
            'min_age_requirement' => 21,
        ]);

        $zone = EntertainmentZone::create([
            'zone_code' => 'ZONE-POOL-01',
            'venue_id' => $venue->id,
            'name' => 'Main Infinity Pool Deck',
            'capacity_limit' => 1200,
            'current_occupancy' => 0,
            'crowd_lockdown_active' => false,
        ]);

        $event = EntertainmentEvent::create([
            'event_code' => 'EVT-SAVAYA-2026',
            'venue_id' => $venue->id,
            'title' => 'Ultra Sunset Beach Rave',
            'doors_open_at' => now()->addDays(10),
            'ticket_floor_price_idr' => 350000,
            'ticket_ceiling_price_idr' => 2500000,
            'current_ticket_price_idr' => 750000,
        ]);

        $tier = TicketTier::create([
            'event_id' => $event->id,
            'zone_id' => $zone->id,
            'name' => 'General Admission Early Bird',
            'tier_code' => 'GA-EB',
            'tier_type' => 'GENERAL_ADMISSION',
            'price_idr' => 750000,
            'quota' => 500,
            'is_numbered_seating' => false,
            'allows_reentry' => false,
        ]);

        $this->assertSame('GA-EB', $tier->tier_code);
        $this->assertSame(500, $tier->quota);
    }

    public function test_tier_kursi_bernomor_memiliki_flag_numbered_seating(): void
    {
        $venue = EntertainmentVenue::create([
            'venue_code' => 'HALL-JAKARTA-01',
            'name' => 'Jakarta International Expo Arena',
            'venue_type' => 'ARENA',
            'city' => 'Jakarta Pusat',
            'max_legal_capacity' => 10000,
            'min_age_requirement' => 18,
        ]);

        $event = EntertainmentEvent::create([
            'event_code' => 'EVT-CONCERT-2026',
            'venue_id' => $venue->id,
            'title' => 'Symphony of the Archipelago',
            'doors_open_at' => now()->addDays(20),
            'ticket_floor_price_idr' => 500000,
            'ticket_ceiling_price_idr' => 5000000,
            'current_ticket_price_idr' => 1500000,
        ]);

        $tierSeated = TicketTier::create([
            'event_id' => $event->id,
            'zone_id' => null,
            'name' => 'VIP Tribune Row A-D',
            'tier_code' => 'VIP-TRIBUNE',
            'tier_type' => 'NUMBERED_SEATS',
            'price_idr' => 2500000,
            'quota' => 200,
            'is_numbered_seating' => true,
            'allows_reentry' => true,
        ]);

        $this->assertTrue($tierSeated->is_numbered_seating);
        $this->assertTrue($tierSeated->allows_reentry);
    }
}
