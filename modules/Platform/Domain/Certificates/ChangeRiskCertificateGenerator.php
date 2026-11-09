<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Certificates;

use Modules\Platform\Domain\Algorithms\ChangeRiskAssessment;

final class ChangeRiskCertificateGenerator
{
    private const SECRET_KEY = 'platform-change-risk-cert-v1';

    public function generateCertificate(string $referenceNumber, ChangeRiskAssessment $assessment, int $timestamp): ChangeRiskCertificate
    {
        $certId = 'CERT-A4-'.strtoupper(bin2hex(random_bytes(8)));
        $payload = json_encode([
            'cert_id' => $certId,
            'ref' => $referenceNumber,
            'score' => $assessment->score,
            'tier' => $assessment->tier->value,
            'factors' => $assessment->factors,
            'ts' => $timestamp,
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, self::SECRET_KEY);

        return new ChangeRiskCertificate(
            $certId,
            $referenceNumber,
            $assessment->score,
            $assessment->tier->value,
            $assessment->factors,
            $timestamp,
            $signature
        );
    }
}
