<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final readonly class AlliancePointsTransferredEvent
{
    public function __construct(
        public string $logId,
        public string $travelPassId,
        public string $partnerCode,
        public int $pointsDebited,
        public int $partnerUnitsCredited,
        public int $transferFeeIdr,
        public int $netPayableIdr
    ) {}
}
