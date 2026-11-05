<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Reinsurance;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

/**
 * Verifier mandiri untuk ProgramAllocationResult (PR2): memeriksa invarian tanpa memakai ProgramAllocator.
 */
final class ProgramAllocationCertificateVerifier
{
    public function verify(ProgramAllocationResult $result): true
    {
        $sum = $result->quotaShareCededIdr + array_sum($result->layerCededIdr) + $result->retainedIdr;
        if ($sum !== $result->grossIdr) {
            throw new InvalidCertificateException("Σ ceded + retained ({$sum}) ≠ bruto ({$result->grossIdr})");
        }

        if ($result->quotaShareCededIdr < 0 || $result->retainedIdr < 0 || $result->netAfterQuotaShareIdr !== $result->grossIdr - $result->quotaShareCededIdr) {
            throw new InvalidCertificateException('Bagian quota share / retensi tidak konsisten');
        }

        $previousAttachment = -1;
        foreach ($result->layers as $layer) {
            if ($layer->attachmentIdr <= $previousAttachment) {
                throw new InvalidCertificateException('Urutan layer tidak menaik menurut attachment');
            }
            $previousAttachment = $layer->attachmentIdr;

            $ceded = $result->layerCededIdr[$layer->code] ?? null;
            if ($ceded === null || $ceded < 0) {
                throw new InvalidCertificateException("Ceded layer {$layer->code} hilang atau negatif");
            }

            $remaining = $layer->aggregateLimitIdr() - ($result->layerUsedBeforeIdr[$layer->code] ?? 0);
            if ($ceded > max($remaining, 0) || $ceded > $layer->limitIdr) {
                throw new InvalidCertificateException("Ceded layer {$layer->code} melebihi limit sisa");
            }
        }

        return true;
    }
}
