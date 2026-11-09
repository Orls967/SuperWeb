<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Telemetry;

/**
 * Generator lalu lintas HTTP sintetis untuk simulasi beban uji sandbox dan pembuktian SLO.
 */
class SyntheticTrafficGenerator
{
    /**
     * @return list<array{endpoint: string, method: string, status_code: int, duration_ms: float}>
     */
    public function generateTrafficSamples(int $count = 100, float $errorProbability = 0.002): array
    {
        $endpoints = [
            ['GET', '/platform/dev'],
            ['GET', '/platform/dev/sandboxes'],
            ['GET', '/platform/dev/slos'],
            ['GET', '/platform/dev/metrics'],
            ['POST', '/platform/dev/sandboxes'],
        ];

        $samples = [];
        for ($i = 0; $i < $count; $i++) {
            $ep = $endpoints[$i % count($endpoints)];
            $isErr = (random_int(1, 10000) / 10000.0) <= $errorProbability;

            $status = $isErr ? 500 : 200;
            $duration = random_int(15, 120) + (random_int(0, 99) / 100.0);

            $samples[] = [
                'endpoint' => $ep[1],
                'method' => $ep[0],
                'status_code' => $status,
                'duration_ms' => $duration,
            ];
        }

        return $samples;
    }
}
