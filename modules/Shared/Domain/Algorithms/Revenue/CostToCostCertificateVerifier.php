<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Revenue;

use RuntimeException;

final class CostToCostCertificateVerifier
{
    public static function verify(CostToCostResult $result): bool
    {
        $expectedHash = hash('sha256', sprintf(
            '%d:%d:%d:%d:%d:%d',
            $result->contractAmountIdr,
            $result->cumulativeCostIdr,
            $result->totalEstimatedCostIdr,
            $result->previouslyRecognizedIdr,
            $result->cumulativeRecognizedIdr,
            $result->periodRevenueIdr,
        ));

        if (! hash_equals($expectedHash, $result->certificateSha256)) {
            throw new RuntimeException("Sertifikat CostToCost tidak valid: {$result->certificateSha256}");
        }

        // Invariant checks
        if ($result->cumulativeCostIdr >= $result->totalEstimatedCostIdr) {
            if ($result->cumulativeRecognizedIdr !== $result->contractAmountIdr) {
                throw new RuntimeException('Kumulatif diakui harus sama dengan nilai kontrak saat biaya kumulatif mencapai atau melampaui estimasi total.');
            }
        }

        if ($result->periodRevenueIdr !== ($result->cumulativeRecognizedIdr - $result->previouslyRecognizedIdr)) {
            throw new RuntimeException('Pendapatan periode harus sama dengan selisih kumulatif diakui dan pengakuan sebelumnya.');
        }

        return true;
    }
}
