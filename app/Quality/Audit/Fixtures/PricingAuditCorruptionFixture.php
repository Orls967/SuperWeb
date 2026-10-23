<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PricingAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'pricing:audit';
    }

    public function corrupt(): string
    {
        DB::table('pric_promotions')->insert([
            'id' => (string) Str::uuid(),
            'code' => 'PROMO-OVER-'.Str::random(5),
            'name' => 'Promo Melebihi Anggaran',
            'mechanic' => 'DISCOUNT',
            'budget_idr' => 1000000,
            'spent_idr' => 5000000, // spent > budget
            'valid_from' => now()->subDays(10)->toDateString(),
            'valid_until' => now()->addDays(10)->toDateString(),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'promo.over_budget';
    }
}
