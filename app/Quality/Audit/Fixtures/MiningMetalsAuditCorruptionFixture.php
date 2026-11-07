<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningSmelterFurnace;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;

final class MiningMetalsAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'mining:audit-metals';
    }

    public function corrupt(): string
    {
        $site = MiningSite::query()->firstOrCreate(
            ['site_code' => 'SITE-CORRUPT-01'],
            [
                'name' => 'Site Audit Test',
                'commodity' => 'NICKEL',
                'timezone' => 'Asia/Makassar',
                'location' => 'Pomalaa',
                'status' => 'ACTIVE',
            ]
        );

        $furnace = MiningSmelterFurnace::query()->firstOrCreate(
            ['furnace_code' => 'FURN-CORRUPT-01'],
            [
                'site_id' => $site->id,
                'name' => 'Corrupt Audit Furnace',
                'technology_type' => 'RKEF',
                'capacity_mva' => 45.0,
                'status' => 'OPERATIONAL',
            ]
        );

        MiningSmelterFurnaceRun::query()->create([
            'furnace_id' => $furnace->id,
            'run_code' => 'RUN-CORRUPT-MASS',
            'ore_feed_wmt' => 10.0,
            'metal_output_tons' => 80.0,
            'slag_output_tons' => 120.0,
            'status' => 'COMPLETED',
        ]);

        return 'METALS DESK AUDIT FAILED';
    }
}
