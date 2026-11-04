<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\Rwa\Contracts\VotingWeightReader;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaHolding;

final class RwaVotingWeightReaderService implements VotingWeightReader
{
    /**
     * @return array<int, int> user_id => token units
     */
    public function tokenUnitsByHolderAt(string $assetCode, CarbonImmutable $at): array
    {
        $asset = RwaAsset::where('token_symbol', $assetCode)->orWhere('underlying_asset_id', $assetCode)->first();
        if (! $asset) {
            return [];
        }

        /** @var Collection<int, RwaHolding> $holdings */
        $holdings = RwaHolding::where('rwa_asset_id', $asset->id)
            ->where('created_at', '<=', $at)
            ->where('token_balance', '>', 0)
            ->get();

        $weights = [];
        foreach ($holdings as $holding) {
            $weights[(int) $holding->user_id] = (int) $holding->token_balance;
        }

        return $weights;
    }
}
