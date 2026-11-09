<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Models\SandboxEnvironment;

/**
 * Health Checker multi-faktor untuk lingkungan sandbox developer.
 */
class SandboxHealthCheckService
{
    /**
     * @return array{is_healthy: bool, checks: array<string, bool>, latency_ms: float}
     */
    public function checkSandboxHealth(SandboxEnvironment $sandbox): array
    {
        $start = microtime(true);

        $checks = [
            'status_valid' => in_array($sandbox->status, ['requested', 'provisioning', 'seeded', 'ready', 'expiring', 'expired', 'destroyed'], true),
            'not_expired' => $sandbox->expires_at === null || $sandbox->expires_at->isFuture(),
            'owner_exists' => $sandbox->owner_id > 0,
            'idempotency_valid' => ! empty($sandbox->idempotency_key),
        ];

        $allHealthy = true;
        foreach ($checks as $passed) {
            if (! $passed) {
                $allHealthy = false;
                break;
            }
        }

        $elapsed = (microtime(true) - $start) * 1000.0;

        return [
            'is_healthy' => $allHealthy,
            'checks' => $checks,
            'latency_ms' => round($elapsed, 2),
        ];
    }
}
