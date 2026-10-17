<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\ZoneUtilityBilling;

final class PrpAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'prp:audit';
    }

    public function corrupt(): string
    {
        $zone = BuildingZone::firstOrCreate(
            ['zone_code' => 'ZN-CORRUPT-PRP-01'],
            [
                'building_code' => 'BLD-CORRUPT-01',
                'name' => 'Corrupted HVAC Floor',
                'floor_level' => '1',
                'area_sqm' => 100.0,
                'current_temp_c' => 20.0,
                'current_occupancy' => 5,
                'current_kwh_rate' => 2000.0,
            ]
        );

        // Corrupted billing: 100 kWh * 2000 IDR should be 200,000 IDR, but recorded as 999,999,999 IDR!
        ZoneUtilityBilling::create([
            'billing_code' => 'BLG-CORRUPT-'.Str::random(4),
            'zone_id' => $zone->id,
            'tenant_id' => 99,
            'billing_date' => Carbon::now()->toDateString(),
            'total_kwh' => 100.0,
            'total_amount_idr' => 999999999,
            'carbon_emission_kg' => 85.0,
            'status' => 'BILLED',
        ]);

        return 'Proptech audit FAILED';
    }
}
