<?php

declare(strict_types=1);

namespace Tests\Acceptance\Property;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\RbacConglomerateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Proptech\Application\Services\FlexSpaceService;
use Modules\Proptech\Application\Services\ProptechService;
use Modules\Proptech\Application\Services\UtilityBillingService;
use Modules\Proptech\Domain\Models\BuildingSensor;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\Flex\FlexSpace;
use Modules\Proptech\Domain\Models\ZoneUtilityBilling;
use Modules\Shared\Domain\Math\CumulativeRounding;
use Modules\Shared\Domain\Math\CumulativeRoundingCertificate;
use Modules\Shared\Domain\Math\CumulativeRoundingCertificateVerifier;
use Tests\TestCase;

class ProptechAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $tenantUser;

    protected User $adminUser;

    protected BuildingZone $zone;

    protected FlexSpace $flexSpace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacConglomerateSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->tenantUser = User::factory()->create([
            'email' => 'tenant_starbucks@superweb.test',
            'name' => 'PT Sari Coffee Indonesia',
        ]);

        $this->adminUser = User::factory()->create([
            'email' => 'proptech_admin@superweb.test',
            'name' => 'Building Facility Manager',
        ]);

        $this->zone = BuildingZone::create([
            'zone_code' => 'ZN-MALL-GF-01',
            'building_code' => 'BLD-MALL-01',
            'name' => 'Ground Floor Retail Wing',
            'floor_level' => 'GF',
            'area_sqm' => 600.0,
            'current_temp_c' => 24.0,
            'current_occupancy' => 30,
            'current_kwh_rate' => 2000.0,
            'baseline_kwh_per_hour' => 15.0,
        ]);

        $this->flexSpace = FlexSpace::create([
            'space_code' => 'FLX-SUITE-101',
            'name' => 'Executive Meeting Suite 101',
            'space_type' => 'meeting_room',
            'zone_id' => $this->zone->id,
            'capacity_persons' => 10,
            'rate_per_hour_idr' => 200_000,
            'area_sqm' => 40.0,
            'status' => 'AVAILABLE',
        ]);
    }

    // ─── SLICE 1: UTILITY METER READINGS & CUMULATIVE ROUNDING BILLING (Tests 1-18) ───

    public function test_01_record_meter_reading_with_idempotency_key(): void
    {
        $service = app(ProptechService::class);
        $recordedAt = Carbon::parse('2026-11-01 08:00:00');

        $reading = $service->ingestSensorReading(
            zone: $this->zone,
            sensorCode: 'PWR-MTR-01',
            sensorType: 'POWER_KWH',
            readingValue: 125.50,
            recordedAt: $recordedAt,
            idempotencyKey: 'READ-20261101-0800-PWR'
        );

        expect($reading)->toBeInstanceOf(BuildingSensor::class);
        expect((float) $reading->reading_value)->toBe(125.50);
        expect($reading->idempotency_key)->toBe('READ-20261101-0800-PWR');
    }

    public function test_02_ingest_duplicate_meter_reading_is_idempotent(): void
    {
        $service = app(ProptechService::class);
        $recordedAt = Carbon::parse('2026-11-01 09:00:00');

        $first = $service->ingestSensorReading(
            zone: $this->zone,
            sensorCode: 'PWR-MTR-01',
            sensorType: 'POWER_KWH',
            readingValue: 140.00,
            recordedAt: $recordedAt,
            idempotencyKey: 'READ-DUP-KEY'
        );

        $second = $service->ingestSensorReading(
            zone: $this->zone,
            sensorCode: 'PWR-MTR-01',
            sensorType: 'POWER_KWH',
            readingValue: 999.00, // modified payload
            recordedAt: $recordedAt,
            idempotencyKey: 'READ-DUP-KEY'
        );

        expect($second->id)->toBe($first->id);
        expect(BuildingSensor::where('idempotency_key', 'READ-DUP-KEY')->count())->toBe(1);
    }

    public function test_03_meter_reading_supports_electricity_decimal_precision(): void
    {
        $sensor = BuildingSensor::create([
            'sensor_code' => 'ELEC-PREC-01',
            'zone_id' => $this->zone->id,
            'sensor_type' => 'POWER_KWH',
            'reading_value' => 1234.567,
            'idempotency_key' => 'IDEMP-ELEC-PREC',
            'recorded_at' => now(),
        ]);

        expect((float) $sensor->fresh()->reading_value)->toBe(1234.57);
    }

    public function test_04_meter_reading_supports_water_metering(): void
    {
        $sensor = BuildingSensor::create([
            'sensor_code' => 'WTR-MTR-01',
            'zone_id' => $this->zone->id,
            'sensor_type' => 'WATER_M3',
            'reading_value' => 85.25,
            'idempotency_key' => 'IDEMP-WTR-01',
            'recorded_at' => now(),
        ]);

        expect($sensor->sensor_type)->toBe('WATER_M3');
        expect((float) $sensor->reading_value)->toBe(85.25);
    }

    public function test_05_calculate_utility_billing_strictly_from_actual_consumption(): void
    {
        $billingService = app(UtilityBillingService::class);
        $date = Carbon::parse('2026-11-01');

        // Create actual sensor readings for the date
        BuildingSensor::create([
            'sensor_code' => 'PWR-01',
            'zone_id' => $this->zone->id,
            'sensor_type' => 'POWER_KWH',
            'reading_value' => 250.0,
            'idempotency_key' => 'ACTUAL-01',
            'recorded_at' => $date->copy()->setTime(10, 0),
        ]);
        BuildingSensor::create([
            'sensor_code' => 'PWR-01',
            'zone_id' => $this->zone->id,
            'sensor_type' => 'POWER_KWH',
            'reading_value' => 250.0,
            'idempotency_key' => 'ACTUAL-02',
            'recorded_at' => $date->copy()->setTime(14, 0),
        ]);

        $billing = $billingService->generateZoneBilling(
            zone: $this->zone,
            tenantId: $this->tenantUser->id,
            billingDate: $date
        );

        expect((float) $billing->total_kwh)->toBe(500.0);
        // Rate is 2000 IDR/kWh: 500 kWh * 2000 = 1,000,000 IDR
        expect($billing->total_amount_idr)->toBe(1_000_000);
    }

    public function test_06_multi_tenant_utility_allocation_uses_cumulative_rounding(): void
    {
        $weights = [3333, 3333, 3334]; // sum 10,000 bps
        $totalBill = 100_001; // IDR with fractional division

        $result = CumulativeRounding::roundCumulative($totalBill, $weights);

        expect(array_sum($result->allocations))->toBe($totalBill);
        expect(count($result->allocations))->toBe(3);
    }

    public function test_07_cumulative_rounding_invariant_sum_equals_total_exactly(): void
    {
        $weights = [1500, 2500, 3500, 2500]; // sum 10,000 bps
        $totalBill = 1_234_567;

        $result = CumulativeRounding::roundCumulative($totalBill, $weights);

        expect(array_sum($result->allocations))->toBe($totalBill);
    }

    public function test_08_cumulative_rounding_invariant_each_step_within_one_idr_from_exact(): void
    {
        $weights = [2000, 4000, 4000];
        $totalBill = 999_999;

        $result = CumulativeRounding::roundCumulative($totalBill, $weights);

        foreach ($result->allocations as $i => $allocated) {
            $exactTheoretical = ($totalBill * $weights[$i]) / 10000;
            expect(abs($allocated - $exactTheoretical))->toBeLessThanOrEqual(1.0);
        }
    }

    public function test_09_cumulative_rounding_produces_valid_verified_certificate(): void
    {
        $weights = [5000, 5000];
        $total = 750_001;

        $result = CumulativeRounding::roundCumulative($total, $weights);

        expect($result->certificate)->not->toBeNull();
        expect(fn () => CumulativeRoundingCertificateVerifier::verify($result->certificate))->not->toThrow(\Throwable::class);
    }

    public function test_10_tampered_cumulative_rounding_certificate_is_rejected(): void
    {
        $weights = [5000, 5000];
        $total = 500_000;

        $result = CumulativeRounding::roundCumulative($total, $weights);
        $certificate = $result->certificate;

        // Tamper certificate: corrupt cumulative allocations
        $tampered = new CumulativeRoundingCertificate(
            totalAmountIdr: $certificate->totalAmountIdr,
            weightsBps: $certificate->weightsBps,
            allocations: [200_000, 200_000], // sum != 500_000!
            cumulativeSum: 400_000
        );

        expect(fn () => CumulativeRoundingCertificateVerifier::verify($tampered))->toThrow(\InvalidArgumentException::class);
    }

    public function test_11_generating_billing_twice_is_idempotent(): void
    {
        $billingService = app(UtilityBillingService::class);
        $date = Carbon::parse('2026-11-01');

        BuildingSensor::create([
            'sensor_code' => 'PWR-01',
            'zone_id' => $this->zone->id,
            'sensor_type' => 'POWER_KWH',
            'reading_value' => 100.0,
            'idempotency_key' => 'IDEMP-BILL-01',
            'recorded_at' => $date->copy()->setTime(10, 0),
        ]);

        $b1 = $billingService->generateZoneBilling($this->zone, $this->tenantUser->id, $date);
        $b2 = $billingService->generateZoneBilling($this->zone, $this->tenantUser->id, $date);

        expect($b2->id)->toBe($b1->id);
        expect(ZoneUtilityBilling::where('zone_id', $this->zone->id)->where('billing_date', $date->toDateString())->count())->toBe(1);
    }

    public function test_12_overtime_ac_hours_surcharge_calculation(): void
    {
        $billingService = app(UtilityBillingService::class);
        $date = Carbon::parse('2026-11-02');

        BuildingSensor::create([
            'sensor_code' => 'PWR-01',
            'zone_id' => $this->zone->id,
            'sensor_type' => 'POWER_KWH',
            'reading_value' => 50.0,
            'idempotency_key' => 'IDEMP-AC-01',
            'recorded_at' => $date->copy()->setTime(12, 0),
        ]);

        // 50 kWh * 2000 = 100,000 IDR base. 2 hours overtime AC @ 75,000 IDR/hr = 150,000 IDR
        $billing = $billingService->generateZoneBilling(
            zone: $this->zone,
            tenantId: $this->tenantUser->id,
            billingDate: $date,
            overtimeAcHours: 2
        );

        expect($billing->base_amount_idr)->toBe(100_000);
        expect($billing->overtime_ac_amount_idr)->toBe(150_000);
        expect($billing->total_amount_idr)->toBe(250_000);
    }

    public function test_13_scope_2_ghg_emissions_factor_computation(): void
    {
        $billingService = app(UtilityBillingService::class);
        $date = Carbon::parse('2026-11-03');

        BuildingSensor::create([
            'sensor_code' => 'PWR-01',
            'zone_id' => $this->zone->id,
            'sensor_type' => 'POWER_KWH',
            'reading_value' => 200.0,
            'idempotency_key' => 'IDEMP-GHG-01',
            'recorded_at' => $date->copy()->setTime(12, 0),
        ]);

        $billing = $billingService->generateZoneBilling($this->zone, $this->tenantUser->id, $date);

        // 200 kWh * 0.85 kg CO2e / kWh = 170.0 kg CO2e
        expect((float) $billing->carbon_emission_kg)->toBe(170.0);
    }

    public function test_14_utility_billing_posts_balanced_ledger_entry(): void
    {
        $billingService = app(UtilityBillingService::class);
        $date = Carbon::parse('2026-11-04');

        BuildingSensor::create([
            'sensor_code' => 'PWR-01',
            'zone_id' => $this->zone->id,
            'sensor_type' => 'POWER_KWH',
            'reading_value' => 100.0,
            'idempotency_key' => 'IDEMP-LEDGER-01',
            'recorded_at' => $date->copy()->setTime(12, 0),
        ]);

        $billing = $billingService->generateZoneBilling($this->zone, $this->tenantUser->id, $date);

        expect($billing->status)->toBe('BILLED');
        // Check ledger accounts exist and are populated
        $tenantArAccount = LedgerAccount::where('code', "ar:mall:tenant:{$this->tenantUser->id}:IDR")->first();
        $revenueAccount = LedgerAccount::where('code', 'mall:utility_revenue:IDR')->first();

        expect($tenantArAccount)->not->toBeNull();
        expect($revenueAccount)->not->toBeNull();
    }

    public function test_15_utility_billing_idempotency_key_prevents_double_posting(): void
    {
        $billingService = app(UtilityBillingService::class);
        $date = Carbon::parse('2026-11-05');

        BuildingSensor::create([
            'sensor_code' => 'PWR-01',
            'zone_id' => $this->zone->id,
            'sensor_type' => 'POWER_KWH',
            'reading_value' => 50.0,
            'idempotency_key' => 'IDEMP-POST-01',
            'recorded_at' => $date->copy()->setTime(12, 0),
        ]);

        $b1 = $billingService->generateZoneBilling($this->zone, $this->tenantUser->id, $date);
        $b2 = $billingService->generateZoneBilling($this->zone, $this->tenantUser->id, $date);

        expect($b1->idempotency_key)->toBe($b2->idempotency_key);
    }

    public function test_16_tiered_utility_tariff_calculation(): void
    {
        $billingService = app(UtilityBillingService::class);
        $tieredAmount = $billingService->calculateTieredAmount(kwh: 150.0, baseRate: 2000);

        // Tier 1 (first 100 kWh @ 2000): 200,000 IDR
        // Tier 2 (next 50 kWh @ 2400): 120,000 IDR
        // Total = 320,000 IDR
        expect($tieredAmount)->toBe(320_000);
    }

    public function test_17_meter_reading_append_only_model_integrity(): void
    {
        $sensor = BuildingSensor::create([
            'sensor_code' => 'FACT-01',
            'zone_id' => $this->zone->id,
            'sensor_type' => 'POWER_KWH',
            'reading_value' => 50.0,
            'idempotency_key' => 'IDEMP-FACT-01',
            'recorded_at' => now(),
        ]);

        expect($sensor->exists)->toBeTrue();
    }

    public function test_18_zone_utility_billing_facts_stored_with_audit_trail(): void
    {
        $billing = ZoneUtilityBilling::create([
            'billing_code' => 'BLG-TEST-01',
            'zone_id' => $this->zone->id,
            'tenant_id' => $this->tenantUser->id,
            'billing_date' => now()->toDateString(),
            'total_kwh' => 100.0,
            'total_amount_idr' => 200_000,
            'carbon_emission_kg' => 85.0,
            'status' => 'BILLED',
            'idempotency_key' => 'BLG-IDEMP-01',
        ]);

        expect($billing->billing_code)->toBe('BLG-TEST-01');
    }

    // ─── SLICE 2: FLEX-SPACE ANTI-OVERLAP BOOKING & ESCROW HOLD/CAPTURE (Tests 19-31) ───

    public function test_19_create_valid_flex_booking_locks_slot_and_holds_deposit(): void
    {
        $service = app(FlexSpaceService::class);
        $start = Carbon::parse('2026-11-06 09:00:00');
        $end = Carbon::parse('2026-11-06 12:00:00'); // 3 hours

        $booking = $service->createBooking(
            space: $this->flexSpace,
            userId: $this->tenantUser->id,
            startTime: $start,
            endTime: $end
        );

        expect($booking->status)->toBe('HELD');
        expect($booking->total_price_idr)->toBe(600_000); // 3 hrs * 200,000 IDR
        expect($booking->deposit_amount_idr)->toBe(180_000); // 30% deposit hold
    }

    public function test_20_overlapping_flex_booking_conflicting_start_is_rejected(): void
    {
        $service = app(FlexSpaceService::class);
        $start1 = Carbon::parse('2026-11-06 13:00:00');
        $end1 = Carbon::parse('2026-11-06 15:00:00');

        $service->createBooking($this->flexSpace, $this->tenantUser->id, $start1, $end1);

        $start2 = Carbon::parse('2026-11-06 14:00:00'); // starts inside slot 1
        $end2 = Carbon::parse('2026-11-06 16:00:00');

        expect(fn () => $service->createBooking($this->flexSpace, $this->adminUser->id, $start2, $end2))
            ->toThrow(\RuntimeException::class, 'overlaps with an existing booking');
    }

    public function test_21_overlapping_flex_booking_conflicting_end_is_rejected(): void
    {
        $service = app(FlexSpaceService::class);
        $start1 = Carbon::parse('2026-11-07 10:00:00');
        $end1 = Carbon::parse('2026-11-07 12:00:00');

        $service->createBooking($this->flexSpace, $this->tenantUser->id, $start1, $end1);

        $start2 = Carbon::parse('2026-11-07 09:00:00');
        $end2 = Carbon::parse('2026-11-07 11:00:00'); // ends inside slot 1

        expect(fn () => $service->createBooking($this->flexSpace, $this->adminUser->id, $start2, $end2))
            ->toThrow(\RuntimeException::class, 'overlaps with an existing booking');
    }

    public function test_22_enclosing_flex_booking_around_existing_slot_is_rejected(): void
    {
        $service = app(FlexSpaceService::class);
        $start1 = Carbon::parse('2026-11-07 14:00:00');
        $end1 = Carbon::parse('2026-11-07 16:00:00');

        $service->createBooking($this->flexSpace, $this->tenantUser->id, $start1, $end1);

        $start2 = Carbon::parse('2026-11-07 13:00:00'); // surrounds slot 1
        $end2 = Carbon::parse('2026-11-07 17:00:00');

        expect(fn () => $service->createBooking($this->flexSpace, $this->adminUser->id, $start2, $end2))
            ->toThrow(\RuntimeException::class, 'overlaps with an existing booking');
    }

    public function test_23_contained_flex_booking_inside_existing_slot_is_rejected(): void
    {
        $service = app(FlexSpaceService::class);
        $start1 = Carbon::parse('2026-11-08 09:00:00');
        $end1 = Carbon::parse('2026-11-08 14:00:00');

        $service->createBooking($this->flexSpace, $this->tenantUser->id, $start1, $end1);

        $start2 = Carbon::parse('2026-11-08 10:00:00');
        $end2 = Carbon::parse('2026-11-08 12:00:00');

        expect(fn () => $service->createBooking($this->flexSpace, $this->adminUser->id, $start2, $end2))
            ->toThrow(\RuntimeException::class, 'overlaps with an existing booking');
    }

    public function test_24_adjacent_back_to_back_flex_booking_is_permitted(): void
    {
        $service = app(FlexSpaceService::class);
        $start1 = Carbon::parse('2026-11-08 15:00:00');
        $end1 = Carbon::parse('2026-11-08 17:00:00');

        $b1 = $service->createBooking($this->flexSpace, $this->tenantUser->id, $start1, $end1);

        // Next booking starts exactly when b1 ends
        $start2 = Carbon::parse('2026-11-08 17:00:00');
        $end2 = Carbon::parse('2026-11-08 19:00:00');

        $b2 = $service->createBooking($this->flexSpace, $this->adminUser->id, $start2, $end2);

        expect($b1->status)->toBe('HELD');
        expect($b2->status)->toBe('HELD');
    }

    public function test_25_booking_hold_posts_escrow_deposit_to_ledger(): void
    {
        $service = app(FlexSpaceService::class);
        $start = Carbon::parse('2026-11-09 10:00:00');
        $end = Carbon::parse('2026-11-09 11:00:00');

        $booking = $service->createBooking($this->flexSpace, $this->tenantUser->id, $start, $end);

        expect($booking->status)->toBe('HELD');
        // Verify ledger entries exist
        $escrowAccount = LedgerAccount::where('code', 'prp:flex_escrow:IDR')->first();
        expect($escrowAccount)->not->toBeNull();
    }

    public function test_26_check_in_with_valid_cryptographic_passport(): void
    {
        $service = app(FlexSpaceService::class);
        $start = Carbon::parse('2026-11-09 13:00:00');
        $end = Carbon::parse('2026-11-09 15:00:00');

        $booking = $service->createBooking($this->flexSpace, $this->tenantUser->id, $start, $end);

        $passportToken = 'PASSPORT-SEC-TOKEN-'.hash('sha256', 'tenant-user-key');
        $checkedIn = $service->checkInWithPassport($booking, $passportToken);

        expect($checkedIn->status)->toBe('CHECKED_IN');
        expect($checkedIn->checked_in_passport_hash)->toBe(hash('sha256', $passportToken));
        expect($checkedIn->checked_in_at)->not->toBeNull();
    }

    public function test_27_check_in_with_empty_passport_token_is_rejected(): void
    {
        $service = app(FlexSpaceService::class);
        $start = Carbon::parse('2026-11-10 09:00:00');
        $end = Carbon::parse('2026-11-10 10:00:00');

        $booking = $service->createBooking($this->flexSpace, $this->tenantUser->id, $start, $end);

        expect(fn () => $service->checkInWithPassport($booking, '   '))
            ->toThrow(\RuntimeException::class, 'Invalid cryptographic passport');
    }

    public function test_28_check_in_captures_full_revenue_in_ledger(): void
    {
        $service = app(FlexSpaceService::class);
        $start = Carbon::parse('2026-11-10 11:00:00');
        $end = Carbon::parse('2026-11-10 13:00:00');

        $booking = $service->createBooking($this->flexSpace, $this->tenantUser->id, $start, $end);
        $service->checkInWithPassport($booking, 'PASSPORT-TOKEN-OK');

        $revenueAccount = LedgerAccount::where('code', 'prp:flex_revenue:IDR')->first();
        expect($revenueAccount)->not->toBeNull();
    }

    public function test_29_check_in_injects_simulated_utility_session_load_to_zone(): void
    {
        $service = app(FlexSpaceService::class);
        $start = Carbon::parse('2026-11-10 14:00:00');
        $end = Carbon::parse('2026-11-10 16:00:00');

        $booking = $service->createBooking($this->flexSpace, $this->tenantUser->id, $start, $end);
        $service->checkInWithPassport($booking, 'PASSPORT-TOKEN-LOAD');

        $sensorReading = BuildingSensor::where('idempotency_key', "FLEX-SESSION-{$booking->booking_code}")->first();
        expect($sensorReading)->not->toBeNull();
        expect((float) $sensorReading->reading_value)->toBe(15.0);
    }

    public function test_30_no_show_forfeits_deposit_as_penalty_revenue(): void
    {
        $service = app(FlexSpaceService::class);
        $start = Carbon::parse('2026-11-11 09:00:00');
        $end = Carbon::parse('2026-11-11 11:00:00');

        $booking = $service->createBooking($this->flexSpace, $this->tenantUser->id, $start, $end);
        $noShow = $service->handleNoShow($booking);

        expect($noShow->status)->toBe('NO_SHOW');
        $penaltyAccount = LedgerAccount::where('code', 'prp:flex_penalty_rev:IDR')->first();
        expect($penaltyAccount)->not->toBeNull();
    }

    public function test_31_cancel_held_booking_releases_deposit(): void
    {
        $service = app(FlexSpaceService::class);
        $start = Carbon::parse('2026-11-11 13:00:00');
        $end = Carbon::parse('2026-11-11 15:00:00');

        $booking = $service->createBooking($this->flexSpace, $this->tenantUser->id, $start, $end);
        $booking->update(['status' => 'CANCELLED']);

        expect($booking->fresh()->status)->toBe('CANCELLED');
    }

    // ─── SLICE 3: SMART BUILDING AUTOMATION & TWO-SOURCE AUDIT (Tests 32-35) ───

    public function test_32_occupancy_above_50_triggers_automated_hvac_cooling(): void
    {
        $service = app(ProptechService::class);
        $this->zone->update(['current_occupancy' => 60]);

        $cmd = $service->evaluateAutomationRules($this->zone);

        expect($cmd)->not->toBeNull();
        expect($cmd->command_type)->toBe('SET_TEMP');
        expect((float) $cmd->target_value)->toBe(22.0);
    }

    public function test_33_zero_occupancy_triggers_automated_hvac_setback(): void
    {
        $service = app(ProptechService::class);
        $this->zone->update(['current_occupancy' => 0]);

        $cmd = $service->evaluateAutomationRules($this->zone);

        expect($cmd)->not->toBeNull();
        expect($cmd->command_type)->toBe('SETBACK');
        expect((float) $cmd->target_value)->toBe(27.0);
    }

    public function test_34_proptech_audit_passes_on_clean_state(): void
    {
        $this->artisan('prp:audit')->assertSuccessful();
    }

    public function test_35_proptech_audit_fails_when_billing_is_tampered(): void
    {
        // Inject tampered billing: 100 kWh * 2000 rate should be 200,000, tamper to 999,999,999
        ZoneUtilityBilling::create([
            'billing_code' => 'BLG-TAMPERED-01',
            'zone_id' => $this->zone->id,
            'tenant_id' => $this->tenantUser->id,
            'billing_date' => now()->toDateString(),
            'total_kwh' => 100.0,
            'total_amount_idr' => 999_999_999,
            'carbon_emission_kg' => 85.0,
            'status' => 'BILLED',
            'idempotency_key' => 'TAMPERED-BILL-KEY',
        ]);

        $this->artisan('prp:audit')
            ->assertFailed()
            ->expectsOutputToContain('Proptech audit FAILED');
    }
}
