<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final readonly class PartnerPointConversionCertificate
{
    public function __construct(
        public string $partnerCode,
        public int $pointsConverted,
        public int $partnerUnitsCredited,
        public int $totalLiabilityDebitedIdr,
        public int $transferFeeIdr,
        public int $netPayableToPartnerIdr,
        public string $signatureHash
    ) {}
}
