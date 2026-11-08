<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class PassengerCareCertificateVerifier
{
    public function verify(PassengerCareCertificate $certificate): bool
    {
        $payload = json_encode([
            'category' => $certificate->category,
            'delay' => $certificate->delayMinutes,
            'duty_of_care' => $certificate->dutyOfCareRequired,
            'cash' => $certificate->cashCompensationMinor,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);

        return hash_equals($expectedHash, $certificate->sha256Hash);
    }
}
