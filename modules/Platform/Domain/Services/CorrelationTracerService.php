<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

/**
 * Service orkestrasi Distributed Trace Context dengan pencatatan span event timeline.
 */
class CorrelationTracerService
{
    public function __construct(
        protected W3cTraceContextPropagator $propagator = new W3cTraceContextPropagator()
    ) {
    }

    /**
     * @param list<array{name: string, duration_ms: float, service: string}> $spans
     * @return array{trace_id: string, total_duration_ms: float, spans: list<array{name: string, duration_ms: float, service: string}>}
     */
    public function buildTimeline(string $traceId, array $spans): array
    {
        $total = 0.0;
        foreach ($spans as $s) {
            $total += $s['duration_ms'];
        }

        return [
            'trace_id' => $traceId,
            'total_duration_ms' => round($total, 2),
            'spans' => $spans,
        ];
    }
}

