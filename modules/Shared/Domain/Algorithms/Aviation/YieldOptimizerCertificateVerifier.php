<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class YieldOptimizerCertificateVerifier
{
    public function verify(YieldOptimizerCertificate $certificate): bool
    {
        $payload = json_encode([
            'capacity' => $certificate->capacity,
            'buckets' => $certificate->buckets,
            'version' => $certificate->algorithmVersion,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);

        return hash_equals($expectedHash, $certificate->sha256Hash);
    }
}

