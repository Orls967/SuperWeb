<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\DTO;

readonly class WestgardQcEvaluationDTO
{
    public function __construct(
        public string $analyzerCode,
        public string $lotNumber,
        public string $controlLevel,
        public string $testCode,
        public float $targetMean,
        public float $targetSd,
        public float $measuredValue,
        public float $zScore,
        public ?string $violation,
        public bool $isRejected,
        public string $description
    ) {}
}
