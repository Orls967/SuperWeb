<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

final readonly class ArtistDoorSplitInput
{
    public function __construct(
        public string $dealType, // 'guarantee', 'door_split', 'versus'
        public int $guaranteeIdr,
        public int $doorGrossIdr,
        public int $approvedExpensesIdr,
        public int $shareBps,
        public int $whtBps,
        public int $advancePaidIdr = 0,
        public string $currency = 'IDR',
        public int $exchangeRateIdr = 1,
    ) {}
}
