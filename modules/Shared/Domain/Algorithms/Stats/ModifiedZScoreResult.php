<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Stats;

use Brick\Math\BigRational;

final class ModifiedZScoreResult
{
    /**
     * @param  list<int>  $values
     * @param  array<string, mixed>  $certificate
     */
    public function __construct(
        public readonly int $x,
        public readonly array $values,
        public readonly BigRational $median,
        public readonly BigRational $mad,
        public readonly ?BigRational $meanAd,
        public readonly BigRational $z,
        public readonly string $zFormatted,
        public readonly bool $isAnomaly,
        public readonly array $certificate
    ) {}

    /**
     * @param  array<string, mixed>  $certificate
     */
    public function withCertificate(array $certificate): self
    {
        return new self(
            $this->x,
            $this->values,
            $this->median,
            $this->mad,
            $this->meanAd,
            $this->z,
            $this->zFormatted,
            $this->isAnomaly,
            $certificate
        );
    }
}

