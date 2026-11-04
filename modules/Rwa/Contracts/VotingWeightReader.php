<?php

declare(strict_types=1);

namespace Modules\Rwa\Contracts;

use Carbon\CarbonImmutable;

interface VotingWeightReader
{
    /**
     * Snapshot token units held by each user for a specific real-world asset at a given timestamp.
     *
     * @return array<int, int> user_id => token units
     */
    public function tokenUnitsByHolderAt(string $assetCode, CarbonImmutable $at): array;
}
