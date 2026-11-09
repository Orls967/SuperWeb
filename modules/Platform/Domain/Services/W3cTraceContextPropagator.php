<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use InvalidArgumentException;

/**
 * Propagator header W3C TraceContext (traceparent & tracestate) lintas microservices/modul.
 */
class W3cTraceContextPropagator
{
    /**
     * Parse header 'traceparent' versi 00 (mis. 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01)
     *
     * @return array{version: string, trace_id: string, parent_id: string, trace_flags: string}
     */
    public function parseTraceparent(string $header): array
    {
        $parts = explode('-', trim($header));
        if (count($parts) !== 4) {
            throw new InvalidArgumentException("Format header traceparent tidak valid: {$header}");
        }

        [$version, $traceId, $parentId, $traceFlags] = $parts;

        if (strlen($traceId) !== 32 || strlen($parentId) !== 16) {
            throw new InvalidArgumentException("Panjang komponen traceparent tidak sesuai spesifikasi W3C: {$header}");
        }

        return [
            'version' => $version,
            'trace_id' => $traceId,
            'parent_id' => $parentId,
            'trace_flags' => $traceFlags,
        ];
    }

    /**
     * Menghasilkan header 'traceparent' baru untuk child span
     */
    public function createChildHeader(string $traceId, ?string $newSpanId = null, bool $sampled = true): string
    {
        $spanId = $newSpanId ?? substr(hash('sha256', (string) microtime(true)), 0, 16);
        $flags = $sampled ? '01' : '00';

        return sprintf('00-%s-%s-%s', $traceId, $spanId, $flags);
    }
}
