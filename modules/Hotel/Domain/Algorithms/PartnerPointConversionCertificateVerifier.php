<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final class PartnerPointConversionCertificateVerifier
{
    public function verify(PartnerPointConversionCertificate $certificate): bool
    {
        $payload = json_encode([
            'partner_code' => $certificate->partnerCode,
            'points_converted' => $certificate->pointsConverted,
            'partner_units' => $certificate->partnerUnitsCredited,
            'liability_debited' => $certificate->totalLiabilityDebitedIdr,
            'transfer_fee' => $certificate->transferFeeIdr,
            'net_payable' => $certificate->netPayableToPartnerIdr,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);

        return hash_equals($expectedHash, $certificate->signatureHash);
    }
}
