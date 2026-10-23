<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Carbon\Carbon;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\ZoneUtilityBilling;

final class ProptechSmartBuildingCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'prp:audit-smartbuilding';
    }

    public function corrupt(): string
    {
        $zone = BuildingZone::firstOrCreate(
            ['zone_code' => 'ZN-CORRUPT-SB-01'],
            [
                'building_code' => 'BLD-CORRUPT-01',
                'name' => 'Corrupted Smart Building Floor',
                'floor_level' => '1',
                'area_sqm' => 100.0,
                'current_temp_c' => 20.0,
                'current_occupancy' => 5,
                'current_kwh_rate' => 2000,
                'baseline_kwh_per_hour' => 10.0,
            ]
        );

        ZoneUtilityBilling::create([
            'billing_code' => 'BLG-CORRUPT-SB-999',
            'zone_id' => $zone->id,
            'tenant_id' => 99,
            'billing_date' => Carbon::now()->toDateString(),
            'total_kwh' => 100.0,
            'overtime_ac_hours' => 0,
            'overtime_ac_amount_idr' => 0,
            'base_amount_idr' => 999999999,
            'total_amount_idr' => 999999999,
            'carbon_emission_kg' => 85.0,
            'status' => 'BILLED',
            'idempotency_key' => 'PRP-CORRUPT-SB-KEY-999',
        ]);

        return 'Proptech smart building audit FAILED';
    }
}
