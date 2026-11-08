<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class CodeshareMileageResult
{
    public function __construct(
        public readonly int $grossFareMinorUnit,
        public readonly int $operatingShareMinorUnit,
        public readonly int $marketingShareMinorUnit,
        public readonly int $milesAccrued,
        public readonly CodeshareMileageCertificate $certificate
    ) {}
}

