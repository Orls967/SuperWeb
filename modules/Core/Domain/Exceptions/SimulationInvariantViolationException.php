<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use RuntimeException;

final class SimulationInvariantViolationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $invariantCode = 'INVARIANT_VIOLATION',
        public readonly ?string $runId = null,
        public readonly ?int $dayNumber = null,
    ) {
        parent::__construct("[{$invariantCode}] {$message}");
    }
}
