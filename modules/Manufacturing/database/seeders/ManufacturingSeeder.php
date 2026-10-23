<?php

declare(strict_types=1);

namespace Modules\Manufacturing\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Manufacturing\Application\Services\ManufacturingService;
use Modules\Manufacturing\Domain\Models\Plant;
use Modules\Resto\Contracts\RestoUsageReaderInterface;

class ManufacturingSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ManufacturingService::class);

        foreach ([
            ['code' => 'PLT-JKT', 'name' => 'Pabrik Jakarta', 'type' => 'factory'],
            ['code' => 'CK-01', 'name' => 'Dapur Sentral CK-01', 'type' => 'central_kitchen'],
        ] as $data) {
            Plant::firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, [
                    'timezone' => 'Asia/Jakarta',
                    'nominal_capacity_per_day' => 0,
                    'capacity_uom' => 'unit',
                ])
            );
        }

        // 35.7 Tautkan outlet Resto CK-01 (seeder Resto berjalan lebih dulu).
        // Melalui contract RestoUsageReaderInterface.
        $outletId = app(RestoUsageReaderInterface::class)->getOutletIdByCode('CK-01');

        $ck = Plant::where('code', 'CK-01')->firstOrFail();
        $service->attachRestoAdapter($ck, $outletId !== null ? (int) $outletId : null);
    }
}
