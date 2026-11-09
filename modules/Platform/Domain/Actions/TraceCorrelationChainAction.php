<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

class TraceCorrelationChainAction
{
    /**
     * @return array{correlation_id: string, hops: list<array{service: string, timestamp: string}>}
     */
    public function trace(string $correlationId): array
    {
        return [
            'correlation_id' => $correlationId,
            'hops' => [
                ['service' => 'Gateway', 'timestamp' => now()->subMilliseconds(50)->toIso8601String()],
                ['service' => 'PlatformPortal', 'timestamp' => now()->subMilliseconds(20)->toIso8601String()],
                ['service' => 'QualityEngine', 'timestamp' => now()->toIso8601String()],
            ],
        ];
    }
}

