<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final readonly class CrossEcosystemPointsAccrualDTO
{
    public function __construct(
        public string $travelPassId,
        public string $originatingLine,
        public int $ptsAmount,
        public string $referenceCode
    ) {}
}
