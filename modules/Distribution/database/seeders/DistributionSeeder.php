<?php

declare(strict_types=1);

namespace Modules\Distribution\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Distribution\Domain\Models\Tier;

class DistributionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'bronze', 'label' => 'Bronze', 'discount_percent' => 0, 'min_achievement_percent' => 0],
            ['code' => 'silver', 'label' => 'Silver', 'discount_percent' => 2.5, 'min_achievement_percent' => 70],
            ['code' => 'gold', 'label' => 'Gold', 'discount_percent' => 5, 'min_achievement_percent' => 90],
        ] as $tier) {
            Tier::updateOrCreate(['code' => $tier['code']], $tier + ['is_active' => true]);
        }
    }
}
