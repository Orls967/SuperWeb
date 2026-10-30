<?php

declare(strict_types=1);

namespace Tests\Acceptance\Energy;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Egy\Application\Actions\IngestSmartGridMeterTelemetryAction;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\SmartMeter;
use RuntimeException;
use Tests\TestCase;

class SmartMeterIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_ingest_valid_cumulative_reading_produces_interval_delta(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-SMART-001',
            'consumer_property_type' => 'MALL',
            'consumer_property_id' => 'PROP-MALL-01',
            'total_kwh_accumulated' => 0.0,
            'tariff_category' => 'BUSINESS_TOU',
            'status' => 'ACTIVE',
        ]);

        $action = app(IngestSmartGridMeterTelemetryAction::class);

        // Reading 1: 100,000 Wh at T0
        $t0 = CarbonImmutable::parse('2026-10-01 10:00:00');
        $r1 = $action->execute([
            'meter_number' => 'MTR-SMART-001',
            'reading_at' => $t0->toIso8601String(),
            'cumulative_wh' => 100000,
            'idempotency_key' => 'egy:telemetry:MTR-SMART-001:20261001100000',
        ]);

        expect($r1->cumulative_wh)->toBe(100000)
            ->and($r1->delta_wh)->toBe(0);

        // Reading 2: 125,000 Wh at T0 + 15 min
        $t1 = $t0->addMinutes(15);
        $r2 = $action->execute([
            'meter_number' => 'MTR-SMART-001',
            'reading_at' => $t1->toIso8601String(),
            'cumulative_wh' => 125000,
            'idempotency_key' => 'egy:telemetry:MTR-SMART-001:20261001101500',
        ]);

        expect($r2->cumulative_wh)->toBe(125000)
            ->and($r2->delta_wh)->toBe(25000)
            ->and($r2->interval_status)->toBe('OK');
    }

    public function test_duplicate_reading_with_same_timestamp_is_idempotent(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-SMART-002',
            'consumer_property_type' => 'FACTORY',
            'consumer_property_id' => 'PROP-FAC-01',
            'status' => 'ACTIVE',
        ]);

        $action = app(IngestSmartGridMeterTelemetryAction::class);
        $t0 = CarbonImmutable::parse('2026-10-01 10:00:00');

        $r1 = $action->execute([
            'meter_number' => 'MTR-SMART-002',
            'reading_at' => $t0->toIso8601String(),
            'cumulative_wh' => 50000,
            'idempotency_key' => 'egy:telemetry:MTR-SMART-002:20261001100000',
        ]);

        // Duplicate call with identical key and reading
        $r2 = $action->execute([
            'meter_number' => 'MTR-SMART-002',
            'reading_at' => $t0->toIso8601String(),
            'cumulative_wh' => 50000,
            'idempotency_key' => 'egy:telemetry:MTR-SMART-002:20261001100000',
        ]);

        expect($r2->id)->toBe($r1->id)
            ->and(MeterReading::where('meter_id', $meter->id)->count())->toBe(1);
    }

    public function test_out_of_order_reading_is_handled_correctly(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-SMART-003',
            'consumer_property_type' => 'HOSPITAL',
            'consumer_property_id' => 'PROP-HOSP-01',
            'status' => 'ACTIVE',
        ]);

        $action = app(IngestSmartGridMeterTelemetryAction::class);
        $t0 = CarbonImmutable::parse('2026-10-01 10:00:00');

        // Reading at T0 + 30 min arrives FIRST
        $action->execute([
            'meter_number' => 'MTR-SMART-003',
            'reading_at' => $t0->addMinutes(30)->toIso8601String(),
            'cumulative_wh' => 150000,
            'idempotency_key' => 'egy:telemetry:MTR-SMART-003:30',
        ]);

        // Reading at T0 arrives LATER
        $action->execute([
            'meter_number' => 'MTR-SMART-003',
            'reading_at' => $t0->toIso8601String(),
            'cumulative_wh' => 100000,
            'idempotency_key' => 'egy:telemetry:MTR-SMART-003:00',
        ]);

        // Reading at T0 + 15 min arrives LAST (fills the gap)
        $rMiddle = $action->execute([
            'meter_number' => 'MTR-SMART-003',
            'reading_at' => $t0->addMinutes(15)->toIso8601String(),
            'cumulative_wh' => 125000,
            'idempotency_key' => 'egy:telemetry:MTR-SMART-003:15',
        ]);

        expect($rMiddle->delta_wh)->toBe(25000)
            ->and(MeterReading::where('meter_id', $meter->id)->count())->toBe(3);
    }

    public function test_rollover_handled_via_meter_delta(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-SMART-004',
            'consumer_property_type' => 'FACTORY',
            'consumer_property_id' => 'PROP-FAC-02',
            'modulus' => 100000000, // 100M Wh = 100,000 kWh
            'status' => 'ACTIVE',
        ]);

        $action = app(IngestSmartGridMeterTelemetryAction::class);
        $t0 = CarbonImmutable::parse('2026-10-01 10:00:00');

        $action->execute([
            'meter_number' => 'MTR-SMART-004',
            'reading_at' => $t0->toIso8601String(),
            'cumulative_wh' => 99990000, // Near rollover
            'idempotency_key' => 'egy:telemetry:MTR-SMART-004:t0',
        ]);

        // Rollover reading
        $rRollover = $action->execute([
            'meter_number' => 'MTR-SMART-004',
            'reading_at' => $t0->addMinutes(15)->toIso8601String(),
            'cumulative_wh' => 15000, // Rolled over!
            'idempotency_key' => 'egy:telemetry:MTR-SMART-004:t1',
        ]);

        // Delta = 100,000,000 - 99,990,000 + 15,000 = 25,000 Wh
        expect($rRollover->delta_wh)->toBe(25000)
            ->and($rRollover->interval_status)->toBe('ROLLOVER');
    }

    public function test_meter_reset_event_handled_with_reset_status(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-SMART-005',
            'consumer_property_type' => 'OFFICE',
            'consumer_property_id' => 'PROP-OFF-01',
            'status' => 'ACTIVE',
        ]);

        $action = app(IngestSmartGridMeterTelemetryAction::class);
        $t0 = CarbonImmutable::parse('2026-10-01 10:00:00');

        $action->execute([
            'meter_number' => 'MTR-SMART-005',
            'reading_at' => $t0->toIso8601String(),
            'cumulative_wh' => 800000,
            'idempotency_key' => 'egy:telemetry:MTR-SMART-005:t0',
        ]);

        // Hardware replacement or recalibration reset event
        $rReset = $action->execute([
            'meter_number' => 'MTR-SMART-005',
            'reading_at' => $t0->addMinutes(15)->toIso8601String(),
            'cumulative_wh' => 5000,
            'is_meter_reset' => true,
            'idempotency_key' => 'egy:telemetry:MTR-SMART-005:t1',
        ]);

        expect($rReset->interval_status)->toBe('RESET')
            ->and($rReset->delta_wh)->toBe(5000);
    }

    public function test_missing_reading_interval_marked_missing_without_silent_estimation(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-SMART-006',
            'consumer_property_type' => 'HOTEL',
            'consumer_property_id' => 'PROP-HOT-01',
            'status' => 'ACTIVE',
        ]);

        $action = app(IngestSmartGridMeterTelemetryAction::class);
        $t0 = CarbonImmutable::parse('2026-10-01 10:00:00');

        $action->execute([
            'meter_number' => 'MTR-SMART-006',
            'reading_at' => $t0->toIso8601String(),
            'cumulative_wh' => 100000,
            'idempotency_key' => 'egy:telemetry:MTR-SMART-006:t0',
        ]);

        // Gap of 45 minutes (skipped 10:15 and 10:30)
        $rGap = $action->execute([
            'meter_number' => 'MTR-SMART-006',
            'reading_at' => $t0->addMinutes(45)->toIso8601String(),
            'cumulative_wh' => 160000,
            'idempotency_key' => 'egy:telemetry:MTR-SMART-006:t3',
        ]);

        // Missing intervals must be flagged and not estimated silently
        expect($rGap->interval_status)->toBe('MISSING_INTERVAL_PREV')
            ->and($rGap->delta_wh)->toBe(60000);
    }

    public function test_meter_reading_is_append_only_and_rejects_update(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-SMART-007',
            'consumer_property_type' => 'MALL',
            'consumer_property_id' => 'PROP-MALL-02',
            'status' => 'ACTIVE',
        ]);

        $reading = MeterReading::create([
            'id' => (string) Str::uuid(),
            'meter_id' => $meter->id,
            'reading_at' => CarbonImmutable::now(),
            'cumulative_wh' => 50000,
            'delta_wh' => 10000,
            'interval_status' => 'OK',
            'rate_per_kwh_minor' => 1450,
            'is_wbp' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('immutable append-only');

        $reading->update(['delta_wh' => 20000]);
    }

    public function test_meter_reading_is_append_only_and_rejects_delete(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-SMART-008',
            'consumer_property_type' => 'MALL',
            'consumer_property_id' => 'PROP-MALL-02',
            'status' => 'ACTIVE',
        ]);

        $reading = MeterReading::create([
            'id' => (string) Str::uuid(),
            'meter_id' => $meter->id,
            'reading_at' => CarbonImmutable::now(),
            'cumulative_wh' => 50000,
            'delta_wh' => 10000,
            'interval_status' => 'OK',
            'rate_per_kwh_minor' => 1450,
            'is_wbp' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('immutable append-only');

        $reading->delete();
    }

    public function test_invalid_device_token_returns_401(): void
    {
        $response = $this->postJson('/api/energy/telemetry', [
            'meter_number' => 'MTR-SMART-001',
            'cumulative_wh' => 100000,
        ], [
            'X-Device-Token' => 'invalid-device-secret',
            'Idempotency-Key' => 'egy:telemetry:test:01',
        ]);

        $response->assertStatus(401);
    }

    public function test_missing_idempotency_key_returns_422(): void
    {
        $response = $this->postJson('/api/energy/telemetry', [
            'meter_number' => 'MTR-SMART-001',
            'cumulative_wh' => 100000,
        ]);

        $response->assertStatus(422);
    }

    public function test_device_rate_limiting_triggers_429(): void
    {
        $user = User::factory()->create(['role' => 'grid_operator']);

        // Rapid requests exceed throttle limit
        for ($i = 0; $i < 65; $i++) {
            $response = $this->actingAs($user)->postJson('/api/energy/telemetry', [
                'meter_number' => 'MTR-SMART-001',
                'cumulative_wh' => 100000 + $i,
            ], [
                'Idempotency-Key' => "egy:telemetry:burst:{$i}",
            ]);
        }

        expect($response->status())->toBe(429);
    }

    public function test_unknown_meter_serial_returns_404(): void
    {
        $action = app(IngestSmartGridMeterTelemetryAction::class);

        $this->expectException(ModelNotFoundException::class);
        $action->execute([
            'meter_number' => 'MTR-NON-EXISTENT',
            'reading_at' => CarbonImmutable::now()->toIso8601String(),
            'cumulative_wh' => 100000,
            'idempotency_key' => 'egy:telemetry:unknown:01',
        ]);
    }
}
