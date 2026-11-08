<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class LoadAndBalanceResult
{
    /**
     * @param int $totalWeightKg
     * @param int $totalMoment
     * @param int $cgMm
     * @param bool $isWithinEnvelope
     * @param list<string> $violations
     * @param LoadAndBalanceCertificate $certificate
     */
    public function __construct(
        public readonly int $totalWeightKg,
        public readonly int $totalMoment,
        public readonly int $cgMm,
        public readonly bool $isWithinEnvelope,
        public readonly array $violations,
        public readonly LoadAndBalanceCertificate $certificate
    ) {
    }
}

