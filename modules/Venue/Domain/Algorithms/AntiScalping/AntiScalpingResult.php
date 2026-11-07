<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\AntiScalping;

final readonly class AntiScalpingResult
{
    public function __construct(
        public bool $isAllowed,
        public int $effectivePriceIdr,
        public ?string $rejectionReason = null,
        public ?AntiScalpingCertificate $certificate = null,
    ) {}
}
