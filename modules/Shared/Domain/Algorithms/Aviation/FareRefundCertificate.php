<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class FareRefundCertificate
{
    public function __construct(
        public readonly string $fareClass,
        public readonly int $baseFareMinor,
        public readonly int $taxMinor,
        public readonly ?int $hoursBeforeFlight,
        public readonly bool $isCancelledByAirline,
        public readonly int $refundPercentageBps,
        public readonly int $baseRefundMinor,
        public readonly int $adminFeeMinor,
        public readonly int $taxRefundMinor,
        public readonly int $totalRefundMinor
    ) {
    }

    public function hash(): string
    {
        return hash('sha256', sprintf(
            '%s|%d|%d|%s|%d|%d|%d|%d|%d|%d',
            $this->fareClass,
            $this->baseFareMinor,
            $this->taxMinor,
            $this->hoursBeforeFlight ?? 'null',
            $this->isCancelledByAirline ? 1 : 0,
            $this->refundPercentageBps,
            $this->baseRefundMinor,
            $this->adminFeeMinor,
            $this->taxRefundMinor,
            $this->totalRefundMinor
        ));
    }
}

