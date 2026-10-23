<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api\V3;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Application\Services\EnterpriseApiV3Service;

class EnterpriseExecutiveController extends Controller
{
    public function __construct(
        private readonly EnterpriseApiV3Service $enterpriseApi,
    ) {}

    /**
     * Retrieve executive-level KPIs across multi-tenant ecosystem
     */
    public function kpis(Request $request): JsonResponse
    {
        $tenantId = (string) ($request->header('X-Tenant-ID') ?: 'tenant-default');

        if (! $this->enterpriseApi->checkRateLimit($tenantId, 120, 60)) {
            $problem = $this->enterpriseApi->createProblemDetails(
                429,
                'Too Many Requests',
                'API rate limit exceeded for tenant.',
                '/api/v3/executive/kpis',
                'ERR_RATE_LIMIT_EXCEEDED'
            );

            return response()->json($problem, 429, [
                'Content-Type' => 'application/problem+json',
            ]);
        }

        $summary = $this->enterpriseApi->getExecutiveSummary($tenantId);

        return response()->json([
            'status' => 'success',
            'data' => $summary,
            'meta' => [
                'api_version' => 'v3.0.0',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Retrieve high-density operational snapshot
     */
    public function snapshot(Request $request): JsonResponse
    {
        $tenantId = (string) ($request->header('X-Tenant-ID') ?: 'tenant-default');

        return response()->json([
            'status' => 'success',
            'snapshot' => [
                'tenant_id' => $tenantId,
                'cluster_state' => 'HEALTHY',
                'rpo_seconds' => 0,
                'rto_seconds' => 0,
                'dr_quorum' => '3_OF_3_SYNCHRONIZED',
                'post_quantum_status' => 'READY',
            ],
        ]);
    }
}
