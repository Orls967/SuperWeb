<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class FareRefundResult
{
    public function __construct(
        public readonly int $baseRefundMinor,
        public readonly int $adminFeeMinor,
        public readonly int $taxRefundMinor,
        public readonly int $totalRefundMinor,
        public readonly int $refundPercentageBps,
        public readonly FareRefundCertificate $certificate
    ) {}
}
