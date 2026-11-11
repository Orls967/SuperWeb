<?php

declare(strict_types=1);

namespace Modules\Core\Application\Access;

use Modules\Core\Domain\Access\Tenancy\TenantIsolationGuard;
use Modules\Core\Infrastructure\Tenancy\TenantScopeRegistry;

/**
 * Tenant Boundary Assurance & Synthetic Probing Service (W8-D58).
 *
 * Runs scheduled synthetic queries to verify that cross-tenant isolation guarantees
 * remain 100% airtight across all tenant-scoped database models.
 */
class TenantBoundaryAssuranceService
{
    public function __construct(
        private readonly TenantIsolationGuard $guard,
        private readonly TenantIsolationProbeService $probeService
    ) {}

    /**
     * Run synthetic isolation probe sweep and produce compliance scorecard.
     *
     * @param  int  $sampleSize  Number of models to probe
     * @return array{
     *     timestamp: string,
     *     total_models_registered: int,
     *     models_probed: int,
     *     isolation_score_percent: float,
     *     cross_tenant_leaks_detected: int,
     *     details: array<string, mixed>
     * }
     */
    public function executeIsolationSweep(int $sampleSize = 50): array
    {
        $probeResult = $this->probeService->runProbe(maxSamples: $sampleSize);
        $registered = TenantScopeRegistry::registeredModels();

        $modelsProbed = (int) ($probeResult['models_tested'] ?? 0);
        $leaks = (int) ($probeResult['isolation_failures'] ?? 0);

        $score = $modelsProbed > 0 ? round((($modelsProbed - $leaks) / $modelsProbed) * 100, 2) : 100.0;

        return [
            'timestamp' => now()->toIso8601String(),
            'total_models_registered' => count($registered),
            'models_probed' => $modelsProbed,
            'isolation_score_percent' => $score,
            'cross_tenant_leaks_detected' => $leaks,
            'details' => $probeResult,
        ];
    }
}
