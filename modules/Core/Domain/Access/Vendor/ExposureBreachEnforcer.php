<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Vendor;

use DomainException;
use Modules\Core\Domain\Models\CoreVendorExposureSnapshot;

/**
 * Vendor Group Exposure Limit & Circuit Breaker Enforcer (W8-D57).
 *
 * Enforces hard and soft exposure caps across supplier clusters.
 * Triggers governance circuit breakers when single-cluster group exposure exceeds Rp 500M.
 */
final class ExposureBreachEnforcer
{
    public const DEFAULT_HARD_CAP_IDR = 500000000; // Rp 500M

    public const WARNING_THRESHOLD_PERCENT = 80;    // Alert at 80% utilization

    /**
     * Evaluate exposure snapshot against policy limits.
     *
     * @return array{
     *     status: string,
     *     is_breached: bool,
     *     is_warning: bool,
     *     utilization_percentage: float,
     *     headroom_idr: int,
     *     action_required: string
     * }
     */
    public function evaluatePolicyLimit(
        CoreVendorExposureSnapshot $snapshot,
        ?int $customLimitIdr = null
    ): array {
        $limit = $customLimitIdr ?? $snapshot->group_credit_limit_idr;
        if ($limit <= 0) {
            $limit = self::DEFAULT_HARD_CAP_IDR;
        }

        $exposure = $snapshot->total_exposure_idr;
        $utilization = round(($exposure / $limit) * 100, 2);
        $headroom = $limit - $exposure;

        $isBreached = $exposure > $limit;
        $isWarning = (! $isBreached) && ($utilization >= self::WARNING_THRESHOLD_PERCENT);

        $status = match (true) {
            $isBreached => 'CRITICAL_BREACH',
            $isWarning => 'WARNING_NEAR_LIMIT',
            default => 'COMPLIANT_NORMAL',
        };

        $action = match ($status) {
            'CRITICAL_BREACH' => 'FREEZE_NEW_COMMITMENTS: Mandatory VP Finance sign-off required for further PO or credit release.',
            'WARNING_NEAR_LIMIT' => 'MONITOR_CLOSELY: Vendor cluster is within 20% of Rp 500M limit.',
            default => 'NO_ACTION_REQUIRED: Exposure well within safe limits.',
        };

        return [
            'status' => $status,
            'is_breached' => $isBreached,
            'is_warning' => $isWarning,
            'utilization_percentage' => $utilization,
            'headroom_idr' => $headroom,
            'action_required' => $action,
        ];
    }

    /**
     * Assert that a proposed commitment does not cause a hard breach.
     *
     * @throws DomainException If transaction causes limit breach
     */
    public function assertSafeToCommit(
        CoreVendorExposureSnapshot $latestSnapshot,
        int $proposedAmountIdr
    ): void {
        $eval = $this->evaluatePolicyLimit($latestSnapshot);

        if ($eval['is_breached']) {
            throw new DomainException(
                "Vendor cluster [{$latestSnapshot->vendor_cluster_code}] is already in CRITICAL_BREACH ({$eval['utilization_percentage']}%). New commitments are blocked."
            );
        }

        $newTotal = $latestSnapshot->total_exposure_idr + $proposedAmountIdr;
        if ($newTotal > $latestSnapshot->group_credit_limit_idr) {
            $excess = $newTotal - $latestSnapshot->group_credit_limit_idr;
            throw new DomainException(
                "Proposed commitment of Rp {$proposedAmountIdr} breaches group limit by Rp {$excess}."
            );
        }
    }
}
