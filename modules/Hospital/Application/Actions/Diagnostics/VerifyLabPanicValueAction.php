<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Diagnostics;

use Modules\Hospital\Application\Services\CriticalValuePanicAlertCoordinator;
use Modules\Hospital\Domain\DTO\CriticalPanicEvaluationDTO;

class VerifyLabPanicValueAction
{
    public function __construct(
        protected CriticalValuePanicAlertCoordinator $coordinator
    ) {}

    public function execute(string $testCode, float $numericValue): CriticalPanicEvaluationDTO
    {
        $eval = $this->coordinator->evaluateCriticalPanic($testCode, $numericValue);

        return new CriticalPanicEvaluationDTO(
            testCode: $testCode,
            measuredValue: $numericValue,
            isPanic: $eval['is_panic'],
            protocol: $eval['protocol'],
            message: $eval['message'],
            panicType: $eval['panic_type'] ?? null,
            threshold: $eval['threshold'] ?? null
        );
    }
}
