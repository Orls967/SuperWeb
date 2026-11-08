<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class SlotCoordinationResult
{
    /**
     * @param array<string, int> $retained
     * @param array<string, int> $allocated
     * @param array<string, int> $waitlisted
     * @param int $remainingPool
     * @param SlotCoordinationCertificate $certificate
     */
    public function __construct(
        public readonly array $retained,
        public readonly array $allocated,
        public readonly array $waitlisted,
        public readonly int $remainingPool,
        public readonly SlotCoordinationCertificate $certificate
    ) {
    }
}

