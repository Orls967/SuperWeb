<?php

declare(strict_types=1);

namespace Modules\Proptech\database\seeders;

use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Modules\Proptech\Domain\Models\BuildingSensor;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\PrescriptiveOp;
use Modules\Proptech\Domain\Models\ZoneUtilityBilling;

class ProptechDemoSeeder extends Seeder
{
    public function run(): void
    {
        (new LedgerAccountsSeeder)->run();

        $zone = BuildingZone::firstOrCreate(
            ['zone_code' => 'ZN-TOWER-A-L10'],
            [
                'building_code' => 'BLD-SCBD-01',
                'name' => 'SCBD Tower A Floor 10 Co-working',
                'floor_level' => '10',
                'area_sqm' => 600.0,
                'current_temp_c' => 23.5,
                'current_occupancy' => 35,
                'current_kwh_rate' => 2000.0,
                'baseline_kwh_per_sqm' => 12.5,
            ]
        );

        BuildingSensor::firstOrCreate(
            ['sensor_code' => 'SNS-TEMP-L10-01'],
            [
                'zone_id' => $zone->id,
                'sensor_type' => 'TEMP',
                'reading_value' => 23.5,
                'is_online' => true,
                'last_ping_at' => Carbon::now(),
                'idempotency_key' => hash('sha256', "{$zone->id}:SNS-TEMP-L10-01:TEMP:demo"),
                'recorded_at' => Carbon::now(),
            ]
        );

        BuildingSensor::firstOrCreate(
            ['sensor_code' => 'SNS-PWR-L10-01'],
            [
                'zone_id' => $zone->id,
                'sensor_type' => 'POWER_KWH',
                'reading_value' => 150.0,
                'is_online' => true,
                'last_ping_at' => Carbon::now(),
                'idempotency_key' => hash('sha256', "{$zone->id}:SNS-PWR-L10-01:POWER_KWH:demo"),
                'recorded_at' => Carbon::now(),
            ]
        );

        BuildingSensor::firstOrCreate(
            ['sensor_code' => 'SNS-OCC-L10-01'],
            [
                'zone_id' => $zone->id,
                'sensor_type' => 'OCCUPANCY',
                'reading_value' => 35.0,
                'is_online' => true,
                'last_ping_at' => Carbon::now(),
                'idempotency_key' => hash('sha256', "{$zone->id}:SNS-OCC-L10-01:OCCUPANCY:demo"),
                'recorded_at' => Carbon::now(),
            ]
        );

        ZoneUtilityBilling::firstOrCreate(
            ['billing_code' => 'BLG-DEMO-001'],
            [
                'zone_id' => $zone->id,
                'tenant_id' => 1,
                'billing_date' => Carbon::now()->toDateString(),
                'total_kwh' => 150.0,
                'base_amount_idr' => 300000,
                'overtime_ac_hours' => 2,
                'overtime_ac_amount_idr' => 150000,
                'total_amount_idr' => 450000,
                'carbon_emission_kg' => 127.5,
                'status' => 'BILLED',
                'idempotency_key' => 'PRP-BLG-DEMO-001',
            ]
        );

        PrescriptiveOp::firstOrCreate(
            [
                'action_code' => 'PRE-COOL-L10-01',
            ],
            [
                'zone_id' => $zone->id,
                'recommendation_text' => 'Tarif puncak listrik mendekati pk 13:00; pra-pendinginan disarankan selama tarif dasar off-peak.',
                'potential_kwh_saved' => 120.50,
                'potential_saving_idr' => 241000,
                'payback_months' => 1,
                'status' => 'PROPOSED',
            ]
        );
    }
}
