<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class LoadAndBalanceCertificate
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(
        public readonly int $mtowKg,
        public readonly int $totalWeightKg,
        public readonly int $totalMoment,
        public readonly int $cgMm,
        public readonly int $cgForwardLimitMm,
        public readonly int $cgAftLimitMm,
        public readonly bool $isWithinEnvelope,
        public readonly array $violations
    ) {}

    public function hash(): string
    {
        return hash('sha256', sprintf(
            '%d|%d|%d|%d|%d|%d|%d|%s',
            $this->mtowKg,
            $this->totalWeightKg,
            $this->totalMoment,
            $this->cgMm,
            $this->cgForwardLimitMm,
            $this->cgAftLimitMm,
            $this->isWithinEnvelope ? 1 : 0,
            implode(',', $this->violations)
        ));
    }
}
