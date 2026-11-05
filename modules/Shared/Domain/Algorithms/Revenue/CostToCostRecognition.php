<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Revenue;

use InvalidArgumentException;

/**
 * Pengakuan pendapatan metode persentase penyelesaian (cost-to-cost)
 * Kompleksitas: O(1) waktu dan ruang.
 */
final class CostToCostRecognition
{
    public static function calculate(
        int $contractAmountIdr,
        int $cumulativeCostIdr,
        int $totalEstimatedCostIdr,
        int $previouslyRecognizedIdr = 0,
    ): CostToCostResult {
        if ($contractAmountIdr < 0 || $cumulativeCostIdr < 0 || $totalEstimatedCostIdr <= 0 || $previouslyRecognizedIdr < 0) {
            throw new InvalidArgumentException('Nilai biaya dan estimasi harus bernilai valid positif.');
        }

        if ($cumulativeCostIdr >= $totalEstimatedCostIdr) {
            $cumulativeRecognized = $contractAmountIdr;
        } else {
            // HalfUp(kontrak * biaya kumulatif / estimasi total)
            $num = (float) $contractAmountIdr * (float) $cumulativeCostIdr;
            $div = (float) $totalEstimatedCostIdr;
            $cumulativeRecognized = (int) round($num / $div, 0, PHP_ROUND_HALF_UP);
        }

        $periodRevenue = $cumulativeRecognized - $previouslyRecognizedIdr;

        $certificateSha256 = hash('sha256', sprintf(
            '%d:%d:%d:%d:%d:%d',
            $contractAmountIdr,
            $cumulativeCostIdr,
            $totalEstimatedCostIdr,
            $previouslyRecognizedIdr,
            $cumulativeRecognized,
            $periodRevenue,
        ));

        $result = new CostToCostResult(
            contractAmountIdr: $contractAmountIdr,
            cumulativeCostIdr: $cumulativeCostIdr,
            totalEstimatedCostIdr: $totalEstimatedCostIdr,
            previouslyRecognizedIdr: $previouslyRecognizedIdr,
            cumulativeRecognizedIdr: $cumulativeRecognized,
            periodRevenueIdr: $periodRevenue,
            certificateSha256: $certificateSha256,
        );

        CostToCostCertificateVerifier::verify($result);

        return $result;
    }
}
