<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\LifeContingency;

use InvalidArgumentException;

final class TermLifePremiumCertificateVerifier
{
    /**
     * Memverifikasi keabsahan sertifikat aktuaria Term Life A7.
     */
    public function verify(TermLifePremiumResult $result): bool
    {
        $age = $result->age;
        $commutation = $result->commutationTable;

        if (! isset($commutation[$age])) {
            throw new InvalidArgumentException("Komutasi usia masuk {$age} tidak ditemukan dalam sertifikat.");
        }

        $mx0 = $commutation[$age]['mx'];
        $nx0 = $commutation[$age]['nx_comm'];

        $expectedPayload = sprintf(
            'TERMLIFE:A7|AGE:%d|TERM:%d|SI:%d|RATE:%d|NSP:%d|ANN:%d|MX0:%.4f|NX0:%.4f',
            $result->age,
            $result->termYears,
            $result->sumInsuredIdr,
            $result->interestRateBps,
            $result->netSinglePremiumIdr,
            $result->annualNetPremiumIdr,
            $mx0,
            $nx0
        );

        $expectedHash = hash('sha256', $expectedPayload);

        if (! hash_equals($expectedHash, $result->certificateHash)) {
            throw new InvalidArgumentException('Sertifikat hash kalkulasi premi Term Life tidak valid atau telah dimodifikasi.');
        }

        return true;
    }
}
