<?php

declare(strict_types=1);

namespace Modules\Mining\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;

class MiningDemoSeeder extends Seeder
{
    public function run(): void
    {
        $site = MiningSite::firstOrCreate(
            ['site_code' => 'SITE-KUTAI-01'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Kutai Coal Concession Block B',
                'commodity' => 'COAL_THERMAL_GAR4200',
                'location' => 'Kutai Kartanegara, East Kalimantan',
            ]
        );

        $pit = MiningPit::firstOrCreate(
            ['site_id' => $site->id, 'pit_code' => 'PIT-NORTH-01'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'North Pit Seam 14',
                'target_daily_production_ton' => 5000,
                'actual_production_ton' => 3200,
            ]
        );

        MiningEquipment::firstOrCreate(
            ['equipment_code' => 'DT-CAT-777D-01'],
            [
                'id' => (string) Str::uuid(),
                'site_id' => $site->id,
                'type' => 'DUMP_TRUCK',
                'model' => 'Caterpillar 777D',
                'capacity_ton' => 95.0,
                'status' => 'available',
                'engine_hours' => 12450,
            ]
        );
    }
}
