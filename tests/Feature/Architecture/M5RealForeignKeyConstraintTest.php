<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\RbacConglomerateSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MiningDispatchRun;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningReconciliationRun;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\TicketTier;
use Tests\TestCase;

/**
 * Task M5 — Real Foreign Keys (A12) verification test.
 *
 * Memastikan kolom *_id yang merujuk users atau tabel modul sendiri
 * pada tabel yang disentuh M5 dilindungi Foreign Key constraint nyata:
 * nilai ID yang tidak ada di tabel referensi wajib ditolak dengan QueryException.
 */
class M5RealForeignKeyConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacConglomerateSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        // Pastikan SQLite menegakkan foreign key constraint
        DB::statement('PRAGMA foreign_keys = ON;');
    }

    public function test_ven_seat_claims_rejects_non_existent_buyer_user_id(): void
    {
        $venue = EntertainmentVenue::create([
            'venue_code' => 'VEN-FK-01',
            'name' => 'FK Arena',
            'venue_type' => 'STADIUM',
            'city' => 'Jakarta',
            'address' => 'Jl. FK No. 1',
            'max_legal_capacity' => 1000,
            'total_capacity' => 1000,
            'status' => 'ACTIVE',
        ]);

        $zone = EntertainmentZone::create([
            'venue_id' => $venue->id,
            'zone_code' => 'Z1',
            'name' => 'Main Zone',
            'capacity_limit' => 500,
        ]);

        $event = EntertainmentEvent::create([
            'event_code' => 'EVT-FK-01',
            'venue_id' => $venue->id,
            'title' => 'Concert FK',
            'doors_open_at' => now()->addDays(2),
            'ticket_floor_price_idr' => 100000,
            'ticket_ceiling_price_idr' => 500000,
            'current_ticket_price_idr' => 200000,
        ]);

        $tier = TicketTier::create([
            'event_id' => $event->id,
            'zone_id' => $zone->id,
            'name' => 'VIP',
            'tier_code' => 'VIP-01',
            'tier_type' => 'NUMBERED_SEATING',
            'price_idr' => 200000,
            'quota' => 50,
        ]);

        $this->expectException(QueryException::class);

        DB::table('ven_seat_claims')->insert([
            'event_id' => $event->id,
            'tier_id' => $tier->id,
            'section_code' => 'SEC-A',
            'row_label' => 'R1',
            'seat_number' => 1,
            'buyer_user_id' => 9999999, // User tidak ada di tabel users
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_ven_quota_holds_rejects_non_existent_buyer_user_id(): void
    {
        $venue = EntertainmentVenue::create([
            'venue_code' => 'VEN-FK-02',
            'name' => 'FK Arena 2',
            'venue_type' => 'STADIUM',
            'city' => 'Jakarta',
            'address' => 'Jl. FK No. 2',
            'max_legal_capacity' => 1000,
            'total_capacity' => 1000,
            'status' => 'ACTIVE',
        ]);

        $zone = EntertainmentZone::create([
            'venue_id' => $venue->id,
            'zone_code' => 'Z2',
            'name' => 'GA Zone',
            'capacity_limit' => 500,
        ]);

        $event = EntertainmentEvent::create([
            'event_code' => 'EVT-FK-02',
            'venue_id' => $venue->id,
            'title' => 'Concert FK 2',
            'doors_open_at' => now()->addDays(2),
            'ticket_floor_price_idr' => 100000,
            'ticket_ceiling_price_idr' => 500000,
            'current_ticket_price_idr' => 200000,
        ]);

        $tier = TicketTier::create([
            'event_id' => $event->id,
            'zone_id' => $zone->id,
            'name' => 'GA',
            'tier_code' => 'GA-02',
            'tier_type' => 'GENERAL_ADMISSION',
            'price_idr' => 150000,
            'quota' => 100,
        ]);

        $this->expectException(QueryException::class);

        DB::table('ven_quota_holds')->insert([
            'event_id' => $event->id,
            'tier_id' => $tier->id,
            'quantity' => 2,
            'hold_token' => 'HOLD-TEST-FK',
            'idempotency_key' => 'IDEM-TEST-FK',
            'status' => 'HELD',
            'held_until' => now()->addMinutes(15),
            'buyer_user_id' => 9999999, // User tidak ada di tabel users
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_ven_gate_scans_rejects_non_existent_scanned_by_user_id(): void
    {
        $this->expectException(QueryException::class);

        DB::table('ven_gate_scans')->insert([
            'ticket_id' => null,
            'gate_code' => 'GATE-FK-01',
            'direction' => 'IN',
            'scan_result' => 'ALLOWED',
            'scanned_at' => now(),
            'scanned_by_user_id' => 9999999, // User tidak ada di tabel users
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_min_dispatch_assignments_rejects_non_existent_override_by_user_id(): void
    {
        $site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-FK-01',
            'name' => 'Pomalaa Site FK',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar',
            'location' => 'Kolaka',
            'status' => 'ACTIVE',
        ]);

        $pit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $site->id,
            'pit_code' => 'PIT-FK-01',
            'name' => 'Pit FK 1',
            'target_production_ton' => 10000,
            'status' => 'ACTIVE',
        ]);

        $equipment = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $site->id,
            'equipment_code' => 'DT-FK-01',
            'type' => 'DUMP_TRUCK',
            'model' => 'Cat 777E',
            'capacity_ton' => 90.0,
            'status' => 'active',
        ]);

        $run = MiningDispatchRun::create([
            'id' => (string) Str::uuid(),
            'site_id' => $site->id,
            'pit_id' => $pit->id,
            'equipment_id' => $equipment->id,
            'shift_code' => 'SHIFT_A',
            'target_payload_ton' => 90.0,
        ]);

        $this->expectException(QueryException::class);

        DB::table('min_dispatch_assignments')->insert([
            'id' => (string) Str::uuid(),
            'dispatch_run_id' => $run->id,
            'is_override' => true,
            'override_reason' => 'Test override',
            'override_by_user_id' => 9999999, // User tidak ada di tabel users
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_min_reconciliation_investigations_rejects_non_existent_resolved_by_user_id(): void
    {
        $site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-FK-02',
            'name' => 'Pomalaa Site FK 2',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar',
            'location' => 'Kolaka',
            'status' => 'ACTIVE',
        ]);

        $run = MiningReconciliationRun::create([
            'id' => (string) Str::uuid(),
            'run_uuid' => 'REC-RUN-FK-01',
            'site_id' => $site->id,
            'period_start' => now()->subDay(),
            'period_end' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('min_reconciliation_investigations')->insert([
            'id' => (string) Str::uuid(),
            'reconciliation_run_id' => $run->id,
            'investigation_code' => 'INV-FK-01',
            'status' => 'RESOLVED',
            'resolved_by_user_id' => 9999999, // User tidak ada di tabel users
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
