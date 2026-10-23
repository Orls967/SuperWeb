<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;

final class CkAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ck:audit';
    }

    public function corrupt(): string
    {
        // Corrupt cloud kitchen load to exceed hourly capacity: 50 > 20
        CloudKitchen::create([
            'kitchen_code' => 'CK-CORRUPT-99',
            'name' => 'Overloaded Central Kitchen',
            'type' => 'central',
            'hourly_capacity' => 20,
            'current_hourly_load' => 50,
            'service_radius_km' => 15.0,
            'is_active' => true,
        ]);

        return 'Cloud kitchen audit FAILED';
    }
}
