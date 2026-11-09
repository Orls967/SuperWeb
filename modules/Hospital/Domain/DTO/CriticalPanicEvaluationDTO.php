<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\DTO;

readonly class CriticalPanicEvaluationDTO
{
    public function __construct(
        public string $testCode,
        public float $measuredValue,
        public bool $isPanic,
        public string $protocol,
        public string $message,
        public ?string $panicType = null,
        public ?float $threshold = null
    ) {}
}
