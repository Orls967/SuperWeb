<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use RuntimeException;

/**
 * Verifikator sertifikat kriptografis untuk algoritma E13 Load Shedding Knapsack.
 */
final class MicrogridLoadSheddingCertificateVerifier
{
    public function verify(MicrogridLoadSheddingResult $result): bool
    {
        $retainedIds = implode(',', array_column($result->retainedFeeders, 'id'));
        $shedIds = implode(',', array_column($result->shedFeeders, 'id'));

        $expectedPayload = sprintf(
            'LOAD-SHEDDING|CAP:%d|SERVED:%d|SHED:%d|SCORE:%d|RETAINED:[%s]|SHED:[%s]',
            $result->availableGenerationKw,
            $result->totalServedKw,
            $result->totalShedKw,
            $result->totalUtilityScore,
            $retainedIds,
            $shedIds
        );

        if ($expectedPayload !== $result->canonicalPayload) {
            throw new RuntimeException('Payload kanonikal hasil load shedding tidak cocok.');
        }

        $expectedHash = hash('sha256', $expectedPayload);
        if ($expectedHash !== $result->certificateHash) {
            throw new RuntimeException('Hash sertifikat load shedding knapsack tidak valid.');
        }

        return true;
    }
}
