<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Certificates;

final class CanaryConfidenceCertificateGenerator
{
    private const SECRET = 'canary-stat-hypothesis-v1';

    public function generate(int $deploymentId, float $baseRate, float $canRate, float $zScore, string $recommendation, int $sampleCount, int $timestamp): CanaryConfidenceCertificate
    {
        $certId = 'CERT-A6-'.strtoupper(bin2hex(random_bytes(8)));
        $payload = "{$certId}:{$deploymentId}:{$baseRate}:{$canRate}:{$zScore}:{$recommendation}:{$sampleCount}:{$timestamp}";
        $signature = hash_hmac('sha256', $payload, self::SECRET);

        return new CanaryConfidenceCertificate(
            $certId,
            $deploymentId,
            $baseRate,
            $canRate,
            $zScore,
            $recommendation,
            $sampleCount,
            $timestamp,
            $signature
        );
    }
}
