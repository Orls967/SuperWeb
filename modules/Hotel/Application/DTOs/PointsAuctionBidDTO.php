<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final class PointsAuctionBidDTO
{
    public function __construct(
        public string $settlementId,
        public string $travelPassId,
        public string $auctionLotCode,
        public string $auctionTitle,
        public int $winningBidPoints,
        public int $valuationIdr,
        public string $eventDate,
        public string $status,
        public string $settledAt
    ) {}
}

