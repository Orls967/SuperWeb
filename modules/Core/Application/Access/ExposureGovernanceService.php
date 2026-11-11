<?php

declare(strict_types=1);

namespace Modules\Core\Application\Access;

use Modules\Core\Domain\Access\Vendor\ExposureBreachEnforcer;
use Modules\Core\Domain\Models\CoreVendorExposureSnapshot;

/**
 * Vendor Exposure Risk Governance Service (W8-D57).
 *
 * Coordinates group exposure surveillance, circuit breaker enforcement,
 * and executive risk reporting for supplier clusters exceeding Rp 500M.
 */
class ExposureGovernanceService
{
    public function __construct(
        private readonly VendorExposureService $vendorService,
        private readonly ExposureBreachEnforcer $enforcer
    ) {}

    /**
     * Conduct real-time cluster risk surveillance.
     *
     * @return array<string, mixed>
     */
    public function assessClusterRisk(string $vendorClusterCode, int $customLimitIdr = 500000000): array
    {
        $snapshot = CoreVendorExposureSnapshot::where('vendor_cluster_code', $vendorClusterCode)
            ->latest('id')
            ->first();

        if ($snapshot === null) {
            $snapshot = $this->vendorService->aggregateFromAllProviders(
                vendorClusterCode: $vendorClusterCode,
                groupLimitIdr: $customLimitIdr
            );
        }

        $policyEval = $this->enforcer->evaluatePolicyLimit($snapshot, $customLimitIdr);

        return [
            'vendor_cluster_code' => $vendorClusterCode,
            'snapshot_code' => $snapshot->snapshot_code,
            'total_exposure_idr' => $snapshot->total_exposure_idr,
            'group_limit_idr' => $snapshot->group_credit_limit_idr,
            'active_tranches' => $snapshot->active_tranche_count,
            'status' => $policyEval['status'],
            'is_breached' => $policyEval['is_breached'],
            'is_warning' => $policyEval['is_warning'],
            'utilization_percent' => $policyEval['utilization_percentage'],
            'headroom_idr' => $policyEval['headroom_idr'],
            'recommended_action' => $policyEval['action_required'],
            'module_breakdown' => $snapshot->module_breakdown,
        ];
    }
}
