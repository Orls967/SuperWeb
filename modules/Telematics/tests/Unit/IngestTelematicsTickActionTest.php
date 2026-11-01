<?php

declare(strict_types=1);

namespace Modules\Telematics\tests\Unit;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Modules\Core\Contracts\Vehicle;
use Modules\Telematics\Application\Actions\IngestTelematicsTickAction;
use Modules\Telematics\Application\Actions\RecomputeRobustBaselineAction;
use Modules\Telematics\Domain\Events\TelematicsAnomalyDetectedEvent;
use Modules\Telematics\Domain\Models\TelematicsAnomaly;
use Modules\Telematics\Domain\Models\TelematicsBaseline;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Modules\Telematics\Domain\Models\TelematicsSequenceGap;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('ingests telematics tick, asserts all fields, detects sequence gaps, handles critical DTC grounding, and records heartbeat', function (): void {
    Event::fake();

    $user = User::factory()->create();
    $vehicle = Vehicle::create([
        'user_id' => $user->id,
        'plate_number' => 'B9999TEL',
        'status' => 'active',
    ]);

    $rawToken = 'secret-token-xyz';
    $device = TelematicsDevice::create([
        'device_id' => 'DEV-INGEST-01',
        'serial_number' => 'SN-INGEST-01',
        'vehicle_id' => $vehicle->id,
        'token_hash' => hash('sha256', $rawToken),
        'status' => 'ACTIVE',
    ]);

    $baselineAction = new RecomputeRobustBaselineAction;
    $action = new IngestTelematicsTickAction($baselineAction);

    // Initial tick
    $now = Carbon::parse('2026-11-01 10:00:00');
    $tick1 = $action->execute(
        device: $device,
        token: $rawToken,
        sequence: 1,
        recordedAt: $now,
        latitudeE6: -6200000,
        longitudeE6: 106816666,
        rpm: 2500,
        speedKmh: 60.5,
        coolantTempDeciC: 900,
        batteryMv: 12600,
        fuelPermil: 800,
        odometerMeters: 15000,
        dtcCodes: []
    );

    expect($tick1->sequence)->toBe(1)
        ->and($tick1->latitude_e6)->toBe(-6200000)
        ->and($tick1->longitude_e6)->toBe(106816666)
        ->and($tick1->rpm)->toBe(2500)
        ->and($tick1->speed_kmh)->toEqual(60.5)
        ->and($tick1->coolant_temp_deci_c)->toBe(900)
        ->and($tick1->battery_mv)->toBe(12600)
        ->and($tick1->fuel_permil)->toBe(800)
        ->and($tick1->odometer_meters)->toBe(15000)
        ->and($tick1->dtc_codes_json)->toBeNull()
        ->and($tick1->dtc_code)->toBeNull()
        ->and($device->fresh()->last_heartbeat_at->toDateTimeString())->toBe($now->toDateTimeString());

    // Gap tick (seq 5 instead of 2) with critical DTCs
    $later = Carbon::parse('2026-11-01 10:05:00');
    $criticalCodes = ['P0300', 'P0117', 'P0562', 'P0217', 'P0219'];
    foreach ($criticalCodes as $idx => $code) {
        $seq = 5 + $idx;
        $tick = $action->execute(
            device: $device,
            token: $rawToken,
            sequence: $seq,
            recordedAt: $later,
            rpm: 3000,
            speedKmh: 80,
            coolantTempDeciC: 950,
            batteryMv: 12400,
            fuelPermil: 780,
            odometerMeters: 15500,
            dtcCodes: [$code]
        );

        expect($tick->sequence)->toBe($seq)
            ->and($tick->dtc_code)->toBe($code)
            ->and($tick->dtc_codes_json)->toBe([$code])
            ->and($device->fresh()->status)->toBe('GROUNDED')
            ->and($vehicle->fresh()->status)->toBe('grounded');
    }

    // Verify sequence gap recorded
    $gap = TelematicsSequenceGap::where('device_id', $device->device_id)->first();
    expect($gap)->not->toBeNull()
        ->and($gap->expected_sequence)->toBe(2)
        ->and($gap->received_sequence)->toBe(5);

    // Verify idempotency (replay seq 5)
    $tickReplay = $action->execute(
        device: $device,
        token: $rawToken,
        sequence: 5,
        recordedAt: $later,
        rpm: 3000,
        speedKmh: 80
    );
    expect($tickReplay->id)->not->toBeEmpty();
});

it('rejects invalid device token', function (): void {
    $device = TelematicsDevice::create([
        'device_id' => 'DEV-INGEST-02',
        'serial_number' => 'SN-INGEST-02',
        'token_hash' => hash('sha256', 'correct-token'),
        'status' => 'ACTIVE',
    ]);

    $baselineAction = new RecomputeRobustBaselineAction;
    $action = new IngestTelematicsTickAction($baselineAction);

    $action->execute(
        device: $device,
        token: 'wrong-token',
        sequence: 1,
        recordedAt: now()
    );
})->throws(InvalidArgumentException::class, 'Invalid device token');

it('evaluates baseline anomaly and dispatches event', function (): void {
    Event::fake();

    $user = User::factory()->create();
    $vehicle = Vehicle::create([
        'user_id' => $user->id,
        'plate_number' => 'B7777ANM',
        'status' => 'active',
    ]);

    $device = TelematicsDevice::create([
        'device_id' => 'DEV-INGEST-03',
        'serial_number' => 'SN-INGEST-03',
        'vehicle_id' => $vehicle->id,
        'status' => 'ACTIVE',
    ]);

    // Baselines for coolant, battery, fuel
    TelematicsBaseline::create([
        'vehicle_id' => $vehicle->id,
        'metric' => 'coolant_temp_deci_c',
        'sample_count' => 100,
        'median_value' => 850.0,
        'mad_value' => 10.0,
        'calculated_at' => now(),
    ]);

    TelematicsBaseline::create([
        'vehicle_id' => $vehicle->id,
        'metric' => 'battery_mv',
        'sample_count' => 100,
        'median_value' => 12500.0,
        'mad_value' => 100.0,
        'calculated_at' => now(),
    ]);

    TelematicsBaseline::create([
        'vehicle_id' => $vehicle->id,
        'metric' => 'fuel_permil',
        'sample_count' => 100,
        'median_value' => 500.0,
        'mad_value' => 20.0,
        'calculated_at' => now(),
    ]);

    $baselineAction = new RecomputeRobustBaselineAction;
    $action = new IngestTelematicsTickAction($baselineAction);

    $action->execute(
        device: $device,
        token: '',
        sequence: 1,
        recordedAt: now(),
        coolantTempDeciC: 1300,
        batteryMv: 8000,
        fuelPermil: 100
    );

    Event::assertDispatched(TelematicsAnomalyDetectedEvent::class);
    expect(TelematicsAnomaly::where('vehicle_id', $vehicle->id)->count())->toBe(3);
});
