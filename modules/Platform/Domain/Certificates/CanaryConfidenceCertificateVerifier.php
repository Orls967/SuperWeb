<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Certificates;

final class CanaryConfidenceCertificateVerifier
{
    private const SECRET = 'canary-stat-hypothesis-v1';

    public function verify(CanaryConfidenceCertificate $cert): bool
    {
        $payload = "{$cert->certificateId}:{$cert->deploymentId}:{$cert->baselineRate}:{$cert->canaryRate}:{$cert->zScore}:{$cert->recommendation}:{$cert->sampleCount}:{$cert->timestamp}";
        $expected = hash_hmac('sha256', $payload, self::SECRET);

        return hash_equals($expected, $cert->rsaShaSignature);
    }
}
