<?php

declare(strict_types=1);

namespace Modules\Core\Application\Access;

use Illuminate\Support\Str;
use Modules\Core\Domain\Algorithms\Access\VendorExposureAggregator;
use Modules\Core\Domain\Models\CoreVendorExposureSnapshot;
use Modules\Shared\Contracts\VendorExposureProviderInterface;

/**
 * Cross-Line Group Vendor Credit Exposure Service (W8-D57).
 */
class VendorExposureService
{
    public function __construct(
        private readonly VendorExposureAggregator $aggregator
    ) {}

    /**
     * Aggregate exposure tranches across all modular providers registered in the container (W8-D57).
     */
    public function aggregateFromAllProviders(
        string $vendorClusterCode,
        int $groupLimitIdr = 500000000,
        ?string $tenantId = null
    ): CoreVendorExposureSnapshot {
        $allTranches = [];
        $providers = app()->tagged('core.vendor_exposure_provider');

        foreach ($providers as $provider) {
            if ($provider instanceof VendorExposureProviderInterface) {
                $moduleTranches = $provider->collectExposureTranches($vendorClusterCode, $tenantId);
                foreach ($moduleTranches as $t) {
                    $allTranches[] = $t;
                }
            }
        }

        return $this->calculateAndSaveExposure(
            vendorClusterCode: $vendorClusterCode,
            tranches: $allTranches,
            groupLimitIdr: $groupLimitIdr
        );
    }

    /**
     * Calculate and persist vendor group exposure snapshot.
     *
     * @param  list<array{module: string, reference: string, amount_idr: int, status: string}>  $tranches
     */
    public function calculateAndSaveExposure(
        string $vendorClusterCode,
        array $tranches,
        int $groupLimitIdr
    ): CoreVendorExposureSnapshot {
        $now = time();
        $result = $this->aggregator->aggregate(
            vendorClusterCode: $vendorClusterCode,
            tranches: $tranches,
            groupCreditLimitIdr: $groupLimitIdr,
            nowTimestamp: $now
        );

        $code = 'SNAP-'.strtoupper(Str::random(10));

        return CoreVendorExposureSnapshot::create([
            'snapshot_code' => $code,
            'vendor_cluster_code' => $vendorClusterCode,
            'total_exposure_idr' => $result['total_exposure_idr'],
            'group_credit_limit_idr' => $result['group_credit_limit_idr'],
            'active_tranche_count' => $result['active_tranche_count'],
            'is_limit_breached' => $result['is_limit_breached'],
            'module_breakdown' => $result['module_breakdown'],
            'integrity_sha256' => (string) ($result['certificate']['sha256'] ?? ''),
            'calculated_at' => now(),
        ]);
    }

    /**
     * Check whether a new PO or credit tranche can be issued to this vendor cluster.
     *
     * @return array{allowed: bool, reason?: string, remaining_credit_idr: int}
     */
    public function canIssueNewTranche(string $vendorClusterCode, int $additionalAmountIdr): array
    {
        $latestSnapshot = CoreVendorExposureSnapshot::where('vendor_cluster_code', $vendorClusterCode)
            ->latest('id')
            ->first();

        if ($latestSnapshot === null) {
            return [
                'allowed' => true,
                'remaining_credit_idr' => 100000000000,
            ];
        }

        $projectedTotal = $latestSnapshot->total_exposure_idr + $additionalAmountIdr;
        $remaining = $latestSnapshot->group_credit_limit_idr - $projectedTotal;

        if ($projectedTotal > $latestSnapshot->group_credit_limit_idr) {
            return [
                'allowed' => false,
                'reason' => 'EXPOSURE_LIMIT_EXCEEDED',
                'remaining_credit_idr' => $remaining,
            ];
        }

        return [
            'allowed' => true,
            'remaining_credit_idr' => $remaining,
        ];
    }
}
