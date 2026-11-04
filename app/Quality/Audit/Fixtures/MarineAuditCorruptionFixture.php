<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Models\User;
use App\Quality\Audit\CorruptionFixture;
use Modules\Marine\Domain\Models\MarineCycle;
use Modules\Marine\Domain\Models\MarineHarvest;
use Modules\Marine\Domain\Models\MarinePayout;
use Modules\Marine\Domain\Models\MarinePond;

class MarineAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'mar:audit';
    }

    public function corrupt(): string
    {
        $payout = MarinePayout::first();
        if ($payout) {
            $payout->update(['net_payout' => $payout->net_payout + 500_000]);
        } else {
            // Buat relasi lengkap yang valid lalu korup
            $farmer = User::firstOrCreate(
                ['email' => 'audit_corrupt_farmer@test.com'],
                ['name' => 'Corrupt Farmer', 'password' => bcrypt('password'), 'role' => 'farmer']
            );

            $pond = MarinePond::create([
                'code' => 'POND-CORRUPT-01',
                'name' => 'Pond Corrupt',
                'farmer_id' => $farmer->id,
                'capacity_units' => 1000,
                'location' => 'Audit Site',
            ]);

            $cycle = MarineCycle::create([
                'cycle_number' => 'CYC-CORRUPT-01',
                'pond_id' => $pond->id,
                'farmer_id' => $farmer->id,
                'species' => 'VANNAMEI',
                'stocked_count' => 1000,
                'initial_weight_grams_per_fish' => 2,
                'advance_amount' => 1_000_000,
                'status' => 'settled',
                'started_at' => now(),
            ]);

            MarineHarvest::create([
                'harvest_number' => 'HRV-CORRUPT-01',
                'cycle_id' => $cycle->id,
                'harvest_date' => now()->toDateString(),
                'harvested_count' => 800,
                'avg_weight_grams' => 200,
                'total_weight_kg' => 160,
                'gross_amount' => 10_000_000,
                'fcr_scaled' => 1200,
            ]);

            // Seharusnya net = 10jt - 1jt (advance) - 0 (feed) = 9jt, kita set 9.999.999
            MarinePayout::create([
                'cycle_id' => $cycle->id,
                'farmer_id' => $farmer->id,
                'gross_amount' => 10_000_000,
                'advance_deduction' => 1_000_000,
                'feed_loan_deduction' => 0,
                'net_payout' => 9_999_999,
                'settled_at' => now(),
                'idempotency_key' => 'SABOTAGE-MAR-001',
            ]);
        }

        return 'Audit menemukan diskrepansi!';
    }
}
