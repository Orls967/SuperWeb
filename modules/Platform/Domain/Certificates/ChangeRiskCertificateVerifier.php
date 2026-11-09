<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Certificates;

final class ChangeRiskCertificateVerifier
{
    private const SECRET_KEY = 'platform-change-risk-cert-v1';

    public function verifyCertificate(ChangeRiskCertificate $certificate): bool
    {
        $payload = json_encode([
            'cert_id' => $certificate->certificateId,
            'ref' => $certificate->referenceNumber,
            'score' => $certificate->calculatedScore,
            'tier' => $certificate->riskTier,
            'factors' => $certificate->inputFactors,
            'ts' => $certificate->timestamp,
        ], JSON_THROW_ON_ERROR);

        $expectedSignature = hash_hmac('sha256', $payload, self::SECRET_KEY);

        return hash_equals($expectedSignature, $certificate->hmacSignature);
    }
}
