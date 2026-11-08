<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class SlotCoordinationCertificate
{
    public function __construct(
        public readonly int $capacity,
        public readonly int $totalRetained,
        public readonly int $pool,
        public readonly array $allocated,
        public readonly array $waitlisted,
        public readonly int $remainingPool,
        public readonly string $hash = ''
    ) {}

    public function calculateHash(): string
    {
        ksort($this->allocated);
        ksort($this->waitlisted);

        return hash('sha256', sprintf(
            '%d|%d|%d|%s|%s|%d',
            $this->capacity,
            $this->totalRetained,
            $this->pool,
            json_encode($this->allocated),
            json_encode($this->waitlisted),
            $this->remainingPool
        ));
    }
}
