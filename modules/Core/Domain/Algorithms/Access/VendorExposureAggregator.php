<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Access;

use InvalidArgumentException;

/**
 * Multi-module group vendor credit exposure aggregator using integer arithmetic (W8-D57, C3).
 *
 * Avoids IEEE 754 floats by calculating all values in minor units (e.g. cents / IDR integer units).
 */
class VendorExposureAggregator
{
    /**
     * Active liability tranche statuses to aggregate.
     */
    private const ACTIVE_STATUSES = [
        'OPEN',
        'UNINVOICED',
        'HELD',
        'PENDING_BILLING',
        'ACTIVE',
        'COMMITTED',
    ];

    /**
     * Aggregate vendor liabilities across business modules.
     *
     * @param  list<array{module: string, reference: string, amount_idr: int, status: string}>  $tranches
     * @return array{
     *     vendor_cluster_code: string,
     *     total_exposure_idr: int,
     *     group_credit_limit_idr: int,
     *     remaining_credit_limit_idr: int,
     *     active_tranche_count: int,
     *     is_limit_breached: bool,
     *     module_breakdown: array<string, int>,
     *     certificate: array<string, mixed>
     * }
     */
    public function aggregate(
        string $vendorClusterCode,
        array $tranches,
        int $groupCreditLimitIdr,
        int $nowTimestamp
    ): array {
        if ($groupCreditLimitIdr < 0) {
            throw new InvalidArgumentException('Limit kredit grup vendor tidak boleh negatif.');
        }

        $totalExposure = 0;
        $activeTrancheCount = 0;
        $moduleBreakdown = [];

        foreach ($tranches as $tranche) {
            $status = strtoupper(trim((string) ($tranche['status'] ?? '')));
            if (! in_array($status, self::ACTIVE_STATUSES, true)) {
                continue;
            }

            $amount = (int) ($tranche['amount_idr'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $module = (string) ($tranche['module'] ?? 'Unknown');
            $totalExposure += $amount;
            $activeTrancheCount++;

            $moduleBreakdown[$module] = ($moduleBreakdown[$module] ?? 0) + $amount;
        }

        ksort($moduleBreakdown);

        $remainingCredit = $groupCreditLimitIdr - $totalExposure;
        $isLimitBreached = $totalExposure > $groupCreditLimitIdr;

        $certificate = $this->generateCertificate(
            vendorClusterCode: $vendorClusterCode,
            totalExposureIdr: $totalExposure,
            groupCreditLimitIdr: $groupCreditLimitIdr,
            activeTrancheCount: $activeTrancheCount,
            isLimitBreached: $isLimitBreached,
            moduleBreakdown: $moduleBreakdown,
            timestamp: $nowTimestamp
        );

        return [
            'vendor_cluster_code' => $vendorClusterCode,
            'total_exposure_idr' => $totalExposure,
            'group_credit_limit_idr' => $groupCreditLimitIdr,
            'remaining_credit_limit_idr' => $remainingCredit,
            'active_tranche_count' => $activeTrancheCount,
            'is_limit_breached' => $isLimitBreached,
            'module_breakdown' => $moduleBreakdown,
            'certificate' => $certificate,
        ];
    }

    /**
     * @param  array<string, int>  $moduleBreakdown
     * @return array<string, mixed>
     */
    private function generateCertificate(
        string $vendorClusterCode,
        int $totalExposureIdr,
        int $groupCreditLimitIdr,
        int $activeTrancheCount,
        bool $isLimitBreached,
        array $moduleBreakdown,
        int $timestamp
    ): array {
        $payload = [
            'algorithm' => 'VENDOR_EXPOSURE_AGGREGATOR_V1',
            'vendor_cluster_code' => $vendorClusterCode,
            'total_exposure_idr' => $totalExposureIdr,
            'group_credit_limit_idr' => $groupCreditLimitIdr,
            'active_tranche_count' => $activeTrancheCount,
            'is_limit_breached' => $isLimitBreached,
            'module_breakdown' => $moduleBreakdown,
            'timestamp' => $timestamp,
        ];

        $canonicalJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $payload['sha256'] = hash('sha256', $canonicalJson);

        return $payload;
    }
}
