<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Chaos;

/**
 * Simulator rekayasa kekacauan (Chaos Engineering) injeksi latensi dan tingkat kegagalan jaringan.
 */
class LatencyInjectionSimulator
{
    /**
     * @return array{injected_latency_ms: int, simulated_failure: bool}
     */
    public function simulateJitter(int $minLatencyMs = 10, int $maxLatencyMs = 250, float $failureProbability = 0.05): array
    {
        $latency = random_int($minLatencyMs, $maxLatencyMs);
        $randomVal = (float) (random_int(1, 100) / 100.0);
        $isFailure = $randomVal <= $failureProbability;

        return [
            'injected_latency_ms' => $latency,
            'simulated_failure' => $isFailure,
        ];
    }
}
