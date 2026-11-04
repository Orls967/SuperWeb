<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Forecast;

use Brick\Math\BigRational;

final class SimpleExponentialSmoothingResult
{
    /**
     * @param  list<int>  $demands
     * @param  array<string, mixed>  $certificate
     */
    public function __construct(
        public readonly array $demands,
        public readonly int $alphaBps,
        public readonly int $holidayFactorBps,
        public readonly BigRational $level,
        public readonly string $levelFormatted,
        public readonly BigRational $adjustedLevel,
        public readonly string $adjustedLevelFormatted,
        public readonly int $suggestedUnitsPerDay,
        public readonly array $certificate
    ) {}

    /**
     * @param  array<string, mixed>  $certificate
     */
    public function withCertificate(array $certificate): self
    {
        return new self(
            $this->demands,
            $this->alphaBps,
            $this->holidayFactorBps,
            $this->level,
            $this->levelFormatted,
            $this->adjustedLevel,
            $this->adjustedLevelFormatted,
            $this->suggestedUnitsPerDay,
            $certificate
        );
    }
}

