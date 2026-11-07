<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\AntiScalping;

final readonly class AntiScalpingInput
{
    public function __construct(
        public int $originalFaceValueIdr,
        public int $askingPriceIdr,
        public int $transferCount,
        public int $userActiveTicketCount,
        public float $velocitySeconds,
    ) {}
}
