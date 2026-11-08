<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final class MilestoneScheduleCertificateVerifier
{
    public static function verify(MilestoneScheduleCertificate $certificate, string $secretKey = 'superweb-hotel-mice-salt'): bool
    {
        $payload = implode('|', [
            $certificate->contractId,
            $certificate->totalContractAmountIdr,
            $certificate->signingDate,
            $certificate->eventStartDate,
            $certificate->totalMilestonesSumIdr,
            $certificate->isConserved ? '1' : '0',
            json_encode($certificate->milestones, JSON_UNESCAPED_SLASHES),
        ]);

        $expectedHash = hash_hmac('sha256', $payload, $secretKey);

        return hash_equals($expectedHash, $certificate->signatureHash);
    }
}
