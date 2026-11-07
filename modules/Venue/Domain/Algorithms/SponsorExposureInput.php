<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

final readonly class SponsorExposureInput
{
    /**
     * @param  array<int, array{zone_name: string, footfall: int, dwell_weight_bps: int, screen_share_bps: int}>  $zoneDeliveries
     */
    public function __construct(
        public array $zoneDeliveries,
        public int $targetImpressions,
        public int $cpmRateIdr,
        public int $milestoneBudgetAmountIdr,
    ) {}
}
