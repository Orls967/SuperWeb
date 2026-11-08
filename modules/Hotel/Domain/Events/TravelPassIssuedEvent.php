<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final readonly class TravelPassIssuedEvent
{
    public function __construct(
        public string $travelPassId,
        public string $passNumber,
        public string $userReference,
        public string $tier
    ) {}
}
