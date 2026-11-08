<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class LoadAndBalanceResult
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(
        public readonly int $totalWeightKg,
        public readonly int $totalMoment,
        public readonly int $cgMm,
        public readonly bool $isWithinEnvelope,
        public readonly array $violations,
        public readonly LoadAndBalanceCertificate $certificate
    ) {}
}
