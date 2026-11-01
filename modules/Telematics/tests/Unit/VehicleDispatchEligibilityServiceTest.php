<?php

declare(strict_types=1);

namespace Modules\Telematics\tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\Vehicle;
use Modules\Telematics\Application\Services\VehicleDispatchEligibilityService;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('determines vehicle dispatch eligibility correctly', function (): void {
    $service = new VehicleDispatchEligibilityService;

    // 1. Vehicle does not exist -> eligible (default)
    expect($service->isEligibleForDispatch(999))->toBeTrue();

    // 2. Vehicle exists and active -> eligible
    $user = User::factory()->create();

    $vehicle = Vehicle::create([
        'user_id' => $user->id,
        'plate_number' => 'B1234XYZ',
        'status' => 'active',
    ]);
    expect($service->isEligibleForDispatch($vehicle->id))->toBeTrue();

    // 3. Vehicle status is grounded -> not eligible
    $vehicle->update(['status' => 'grounded']);
    expect($service->isEligibleForDispatch($vehicle->id))->toBeFalse();

    // Reset vehicle status to active
    $vehicle->update(['status' => 'active']);

    // 4. Device exists and status is GROUNDED -> not eligible
    $device = TelematicsDevice::create([
        'device_id' => 'DEV-TEST-001',
        'serial_number' => 'SN-123456',
        'vehicle_id' => $vehicle->id,
        'status' => 'GROUNDED',
    ]);
    expect($service->isEligibleForDispatch($vehicle->id))->toBeFalse();

    // 5. Device status is ACTIVE -> eligible
    $device->update(['status' => 'ACTIVE']);
    expect($service->isEligibleForDispatch($vehicle->id))->toBeTrue();
});
