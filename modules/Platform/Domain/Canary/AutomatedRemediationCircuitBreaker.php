<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Canary;

final class AutomatedRemediationCircuitBreaker
{
    private string $state = 'CLOSED'; // CLOSED, OPEN, HALF_OPEN

    private int $failureCount = 0;

    public function recordFailure(): void
    {
        $this->failureCount++;
        if ($this->failureCount >= 3) {
            $this->state = 'OPEN';
        }
    }

    public function recordSuccess(): void
    {
        if ($this->state === 'HALF_OPEN') {
            $this->state = 'CLOSED';
            $this->failureCount = 0;
        }
    }

    public function isOpen(): bool
    {
        return $this->state === 'OPEN';
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function reset(): void
    {
        $this->state = 'CLOSED';
        $this->failureCount = 0;
    }
}
