<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Application\Services\ManufacturingService;
use Modules\Manufacturing\Domain\Models\Plant;

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
        // Query mentah: arsitektur melarang import Domain lintas modul.
        $outletId = DB::table('resto_outlets')
            ->where('code', 'CK-01')
            ->value('id');

        $ck = Plant::where('code', 'CK-01')->firstOrFail();
        $service->attachRestoAdapter($ck, $outletId !== null ? (int) $outletId : null);
    }
}
