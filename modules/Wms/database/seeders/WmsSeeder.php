<?php

declare(strict_types=1);

namespace Modules\Wms\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Wms\Application\Services\WmsService;
use Modules\Wms\Domain\Models\Warehouse;

class WmsSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(WmsService::class);

        $dc = Warehouse::firstOrCreate(
            ['code' => 'DC-BJM'],
            ['name' => 'Pusat Distribusi Banjarmasin', 'kind' => 'dc', 'city' => 'Banjarmasin', 'is_active' => true]
        );

        // Hirarki contoh: zona putaway → rak R1 → bin A-01 (pick-face).
        $zone = $service->createZone($dc, 'PA', 'Putaway', 'putaway');
        $rack = $service->createRack($zone, 'R1', 'Rak 1');
        $service->createBin($rack, 'A-01', 0, true);
        $service->createBin($rack, 'A-02', 0, false);
    }
}
