<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class CodeshareMileageCertificateVerifier
{
    public function verify(CodeshareMileageCertificate $certificate): bool
    {
        $payload = json_encode([
            'gross' => $certificate->grossFareMinor,
            'operating_share' => $certificate->operatingShareMinor,
            'marketing_share' => $certificate->marketingShareMinor,
            'miles' => $certificate->milesAccrued,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);

        return hash_equals($expectedHash, $certificate->sha256Hash);
    }
}
