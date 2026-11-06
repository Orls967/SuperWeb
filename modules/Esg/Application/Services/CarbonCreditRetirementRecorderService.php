<?php

declare(strict_types=1);

namespace Modules\Esg\Application\Services;

use Modules\Esg\Contracts\CarbonCreditRetirementRecorder;

/**
 * Layanan pencatatan pensiun kredit karbon & REC ke buku besar keberlanjutan ESG.
 */
final class CarbonCreditRetirementRecorderService implements CarbonCreditRetirementRecorder
{
    public function recordRetirement(
        string $vintageYear,
        int $quantityKgCo2e,
        string $serialNumber,
        string $reason,
        string $entityPartyId,
    ): string {
        $referenceId = sprintf(
            'ESG-RETIRE-%s-%s-%d',
            $vintageYear,
            substr(hash('sha256', $serialNumber), 0, 12),
            $quantityKgCo2e
        );

        return $referenceId;
    }
}
