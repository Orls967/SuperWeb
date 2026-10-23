<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Models\User;
use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaHolding;

final class RwaAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'rwa:audit';
    }

    public function corrupt(): string
    {
        $user = User::first() ?? User::factory()->create();

        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-CORRUPT-'.Str::random(4),
            'name' => 'Corrupt Ghost Property',
            'underlying_asset_type' => 'COMMERCIAL_PROPERTY',
            'underlying_asset_id' => 'BLD-CORRUPT-01',
            'appraisal_value_idr' => 1000000000,
            'total_supply_tokens' => 100, // Only 100 tokens exist!
            'token_price_idr' => 10000000,
            'status' => 'active',
        ]);

        // Corrupted holding: 500 tokens allocated, which exceeds total supply of 100!
        RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $user->id,
            'token_balance' => 500,
        ]);

        return 'RWA audit FAILED';
    }
}
