<?php

declare(strict_types=1);

namespace Modules\Telematics\tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Contracts\Vehicle;
use Modules\Telematics\Application\Actions\ClearVehicleGroundingAction;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('clears vehicle grounding status and updates device status', function (): void {
    $action = new ClearVehicleGroundingAction;

    $user = User::factory()->create();

    $vehicle = Vehicle::create([
        'user_id' => $user->id,
        'plate_number' => 'B5678XYZ',
        'status' => 'grounded',
    ]);

    $device = TelematicsDevice::create([
        'device_id' => 'DEV-TEST-002',
        'serial_number' => 'SN-123456',
        'vehicle_id' => $vehicle->id,
        'status' => 'GROUNDED',
    ]);

    $res = $action->execute($vehicle->id, 1, 'Maintenance completed');
    expect($res)->toBeTrue();

    expect($device->fresh()->status)->toBe('ACTIVE');
    expect($vehicle->fresh()->status)->toBe('active');
});

it('throws exception if reason notes are empty', function (): void {
    $action = new ClearVehicleGroundingAction;
    $action->execute(1, 1, '   ');
})->throws(InvalidArgumentException::class, 'Reason notes are mandatory to clear vehicle grounding.');
