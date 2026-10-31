<?php

declare(strict_types=1);

namespace Tests\Acceptance\Property\Eksplorasi;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\RbacConglomerateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Proptech\Application\Services\FlexSpaceService;
use Modules\Proptech\Application\Services\ProptechService;
use Modules\Proptech\Domain\Models\BuildingSensor;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\Flex\FlexSpace;
use Tests\TestCase;

/**
 * LINI 6: Properti - Proptech Legacy Codebase Exploration (L6.0).
 *
 * Membuktikan kondisi awal kode lama Proptech sebelum MVP disempurnakan:
 * - Table schema: BENAR (tabel `prp_building_zones`, `prp_building_sensors`, `prp_flex_spaces`, `prp_flex_bookings` ada)
 * - ProptechService::generateZoneUtilityBilling: SEBAGIAN (ada kalkulasi kWh dan ledger, tapi pakai round() biasa tanpa B0′.2 cumulative rounding, dan akun prp:utility_ar:IDR bukan ar:mall:tenant:{id}:IDR)
 * - FlexSpaceService::createBooking: BENAR (ada pencegahan anti-overlap dengan lockForUpdate)
 * - FlexSpaceService ledger escrow: SEBAGIAN (memposting deposit hold, tapi belum mengadopsi escrow:payment:IDR)
 * - ProptechService::auditProptech: BENAR (dua sumber verifikasi billing vs sensor)
 */
class ProptechLegacyExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected BuildingZone $zone;

    protected FlexSpace $flexSpace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacConglomerateSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->user = User::factory()->create([
            'email' => 'proptech_tenant@superweb.test',
            'name' => 'Tenant Nusantara',
        ]);

        $this->zone = BuildingZone::create([
            'zone_code' => 'ZN-TOWER-A1',
            'building_code' => 'BLD-HQ-01',
            'name' => 'Tower A Floor 1 Tenant Wing',
            'floor_level' => '1',
            'area_sqm' => 450.0,
            'current_temp_c' => 24.0,
            'current_occupancy' => 25,
            'current_kwh_rate' => 1850.0,
            'baseline_kwh_per_hour' => 12.5,
        ]);

        $this->flexSpace = FlexSpace::create([
            'space_code' => 'FLX-CONF-A',
            'name' => 'Executive Boardroom A',
            'space_type' => 'meeting_room',
            'capacity_persons' => 12,
            'rate_per_hour_idr' => 250_000,
            'status' => 'AVAILABLE',
        ]);
    }

    public function test_legacy_proptech_tables_exist(): void
    {
        expect(Schema::hasTable('prp_building_zones'))->toBeTrue();
        expect(Schema::hasTable('prp_building_sensors'))->toBeTrue();
        expect(Schema::hasTable('prp_zone_utility_billings'))->toBeTrue();
        expect(Schema::hasTable('prp_flex_spaces'))->toBeTrue();
        expect(Schema::hasTable('prp_flex_bookings'))->toBeTrue();
    }

    public function test_generate_zone_utility_billing_status_sebagian(): void
    {
        $service = app(ProptechService::class);
        $date = Carbon::parse('2026-11-01');

        // Seed 3 interval meter readings with decimal portions
        BuildingSensor::create([
            'sensor_code' => 'MTR-EL-01',
            'zone_id' => $this->zone->id,
            'sensor_type' => 'POWER_KWH',
            'reading_value' => 33.333,
            'idempotency_key' => 'SENS-001',
            'recorded_at' => $date->copy()->setTime(8, 0),
        ]);
        BuildingSensor::create([
            'sensor_code' => 'MTR-EL-01',
            'zone_id' => $this->zone->id,
            'sensor_type' => 'POWER_KWH',
            'reading_value' => 33.333,
            'idempotency_key' => 'SENS-002',
            'recorded_at' => $date->copy()->setTime(12, 0),
        ]);
        BuildingSensor::create([
            'sensor_code' => 'MTR-EL-01',
            'zone_id' => $this->zone->id,
            'sensor_type' => 'POWER_KWH',
            'reading_value' => 33.334,
            'idempotency_key' => 'SENS-003',
            'recorded_at' => $date->copy()->setTime(17, 0),
        ]);

        $billing = $service->generateZoneUtilityBilling(
            zone: $this->zone,
            tenantId: $this->user->id,
            billingDate: $date
        );

        // Billing is successfully created
        expect($billing->status)->toBe('BILLED');
        expect((float) $billing->total_kwh)->toBe(100.0);

        // Proves status SEBAGIAN:
        // 1. In legacy code, rounding is standard round() without cumulative rounding verification certificate (B0′.2)
        expect($billing->total_amount_idr)->toBe(185_000);

        // 2. Uses prp:utility_ar:IDR rather than canonical KONSEP §4F tenant AR account ar:mall:tenant:{id}:IDR
        $legacyArAccount = LedgerAccount::where('code', 'prp:utility_ar:IDR')->first();
        expect($legacyArAccount)->not->toBeNull();
    }

    public function test_flex_space_anti_overlap_booking_status_benar(): void
    {
        $service = app(FlexSpaceService::class);
        $start = Carbon::parse('2026-11-01 10:00:00');
        $end = Carbon::parse('2026-11-01 12:00:00');

        $booking1 = $service->createBooking(
            space: $this->flexSpace,
            userId: $this->user->id,
            startTime: $start,
            endTime: $end
        );

        expect($booking1->status)->toBe('HELD');
        expect($booking1->total_price_idr)->toBe(500_000);

        // Attempt overlapping booking should throw RuntimeException
        $overlappingStart = Carbon::parse('2026-11-01 11:00:00');
        $overlappingEnd = Carbon::parse('2026-11-01 13:00:00');

        expect(fn () => $service->createBooking(
            space: $this->flexSpace,
            userId: $this->user->id,
            startTime: $overlappingStart,
            endTime: $overlappingEnd
        ))->toThrow(\RuntimeException::class, 'overlaps with an existing booking');
    }

    public function test_proptech_smart_building_two_source_audit_status_benar(): void
    {
        $service = app(ProptechService::class);
        $audit = $service->auditSmartBuilding();

        expect($audit['status'])->toBe('HEALTHY');
        expect($audit['discrepancies'])->toBe(0);
    }
}
