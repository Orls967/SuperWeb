<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final class ExhibitionBoothReservedEvent
{
    /**
     * @param  array<string, float>  $utilityLoads
     */
    public function __construct(
        public readonly string $reservationId,
        public readonly string $boothCode,
        public readonly string $exhibitorName,
        public readonly float $boothAreaSqm,
        public readonly int $totalCharge,
        public readonly array $utilityLoads = []
    ) {}
}
