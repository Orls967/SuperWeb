<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

final readonly class ArtistDoorSplitResult
{
    public function __construct(
        public int $grossPayIdr,
        public int $whtIdr,
        public int $netPayableIdr,
        public int $valasMinorCents,
        public ArtistDoorSplitCertificate $certificate,
    ) {}
}
