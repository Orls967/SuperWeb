<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class PassengerReaccommodationCertificateVerifier
{
    public function verify(PassengerReaccommodationCertificate $certificate): bool
    {
        $payload = json_encode([
            'count' => $certificate->passengerCount,
            'queue' => $certificate->prioritizedQueue,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);

        return hash_equals($expectedHash, $certificate->sha256Hash);
    }
}

