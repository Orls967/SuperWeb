<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

final class ReleaseGatePolicyRepository
{
    /**
     * @var array<string, array{min_coverage_pct: float, max_p95_latency_ms: float, max_error_rate_pct: float, require_security_signoff: bool}>
     */
    private const POLICIES = [
        'staging' => [
            'min_coverage_pct' => 80.0,
            'max_p95_latency_ms' => 500.0,
            'max_error_rate_pct' => 1.0,
            'require_security_signoff' => false,
        ],
        'canary' => [
            'min_coverage_pct' => 85.0,
            'max_p95_latency_ms' => 300.0,
            'max_error_rate_pct' => 0.5,
            'require_security_signoff' => false,
        ],
        'production' => [
            'min_coverage_pct' => 90.0,
            'max_p95_latency_ms' => 200.0,
            'max_error_rate_pct' => 0.1,
            'require_security_signoff' => true,
        ],
    ];

    public function getPolicy(string $environment): array
    {
        return self::POLICIES[$environment] ?? self::POLICIES['production'];
    }
}
