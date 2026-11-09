<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

final class CanaryMetricThresholdProfile
{
    /**
     * @var array<string, array{max_error_ratio: float, max_z_score: float, max_p99_latency_increase_pct: float}>
     */
    private const PROFILES = [
        'financial_strict' => [
            'max_error_ratio' => 1.5, // Alert if canary error is 1.5x baseline
            'max_z_score' => 2.0,
            'max_p99_latency_increase_pct' => 10.0,
        ],
        'standard_enterprise' => [
            'max_error_ratio' => 3.0,
            'max_z_score' => 3.0,
            'max_p99_latency_increase_pct' => 25.0,
        ],
        'internal_lenient' => [
            'max_error_ratio' => 5.0,
            'max_z_score' => 3.5,
            'max_p99_latency_increase_pct' => 50.0,
        ],
    ];

    public function getProfile(string $tierName): array
    {
        return self::PROFILES[$tierName] ?? self::PROFILES['standard_enterprise'];
    }
}
