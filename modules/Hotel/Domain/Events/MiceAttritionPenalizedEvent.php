<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final class MiceAttritionPenalizedEvent
{
    public function __construct(
        public readonly string $contractNumber,
        public readonly int $shortfallNights,
        public readonly int $penaltyAmount,
        public readonly string $folioId
    ) {}
}
