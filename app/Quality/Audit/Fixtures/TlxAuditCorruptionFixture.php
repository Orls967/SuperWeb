<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Tlx\Domain\Models\TelecomDataCenter;

final class TlxAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'tlx:audit';
    }

    public function corrupt(): string
    {
        TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-CORRUPT-99',
            'name' => 'Overcapacity Data Center',
            'city' => 'Jakarta',
            'total_facility_power_kw' => 1000.0,
            'it_load_power_kw' => 800.0,
            'measured_pue' => 1.25,
            'total_racks_capacity' => 50,
            'occupied_racks' => 150,
        ]);

        return 'Tlx audit FAILED';
    }
}
