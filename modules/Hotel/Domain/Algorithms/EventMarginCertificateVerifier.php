<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final class EventMarginCertificateVerifier
{
    public static function verify(EventMarginCertificate $certificate, string $secretKey = 'superweb-hotel-mice-salt'): bool
    {
        $payload = implode('|', [
            $certificate->proposalId,
            $certificate->totalRevenueIdr,
            $certificate->totalDirectCostIdr,
            $certificate->grossMarginIdr,
            number_format($certificate->grossMarginPercent, 2, '.', ''),
            number_format($certificate->guardrailThresholdPercent, 2, '.', ''),
            $certificate->isCompliant ? '1' : '0',
            $certificate->status,
            $certificate->priceShortfallIdr,
            json_encode($certificate->revenueBreakdown, JSON_UNESCAPED_SLASHES),
            json_encode($certificate->costBreakdown, JSON_UNESCAPED_SLASHES),
        ]);

        $expectedHash = hash_hmac('sha256', $payload, $secretKey);

        return hash_equals($expectedHash, $certificate->signatureHash);
    }
}
