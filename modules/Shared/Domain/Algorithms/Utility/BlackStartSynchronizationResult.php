<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

final class BlackStartSynchronizationResult
{
    /**
     * @param  list<array{step: int, component_code: string, component_name: string, estimated_seconds: int, inrush_current_amp: int}>  $sequence
     */
    public function __construct(
        public readonly string $microgridCode,
        public readonly int $totalComponentsCount,
        public readonly int $totalEstimatedSeconds,
        public readonly array $sequence,
        public readonly string $certificateHash,
        public readonly string $canonicalPayload,
    ) {}
}
