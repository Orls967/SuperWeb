<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

final readonly class ArtistDoorSplitCertificate
{
    public function __construct(
        public string $dealType,
        public int $guaranteeIdr,
        public int $doorGrossIdr,
        public int $approvedExpensesIdr,
        public int $shareBps,
        public int $whtBps,
        public int $advancePaidIdr,
        public int $grossPayIdr,
        public int $whtIdr,
        public int $netPayableIdr,
        public int $valasMinorCents,
        public string $hmacSignature,
    ) {}

    public function isValid(): bool
    {
        return ArtistDoorSplitVerifier::verify($this);
    }
}
