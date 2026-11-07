<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\AntiScalping;

final readonly class AntiScalpingCertificate
{
    public function __construct(
        public int $originalFaceValueIdr,
        public int $askingPriceIdr,
        public int $effectivePriceIdr,
        public int $maxAllowedPriceIdr,
        public int $transferCount,
        public int $userActiveTicketCount,
        public string $hmacSignature,
    ) {}

    public function isValid(): bool
    {
        return AntiScalpingVerifier::verify($this);
    }
}
