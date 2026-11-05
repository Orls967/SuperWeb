<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use RuntimeException;

final class TawarruqFxCertificateVerifier
{
    /**
     * Memverifikasi integritas sertifikat kalkulasi TawarruqFx.
     *
     * @throws RuntimeException Jika hash tidak cocok atau terdapat manipulasi data
     */
    public function verify(TawarruqFxResult $result): true
    {
        $payload = [
            'orderNumber' => $result->orderNumber,
            'sourceCurrency' => $result->sourceCurrency,
            'sourcePrincipalCents' => $result->sourcePrincipalCents,
            'targetCurrency' => $result->targetCurrency,
            'targetReceivedCents' => $result->targetReceivedCents,
            'spotRateBps' => $result->spotRateBps,
            'tenorDays' => $result->tenorDays,
            'markupRateBps' => $result->markupRateBps,
            'markupProfitCents' => $result->markupProfitCents,
            'brokerFeeCents' => $result->brokerFeeCents,
            'totalDeferredPriceCents' => $result->totalDeferredPriceCents,
            'effectiveRateBps' => $result->effectiveRateBps,
            'commodityLotSerial' => $result->commodityLotSerial,
            'exchangeName' => $result->exchangeName,
            'evaluatedAt' => $result->evaluatedAt,
        ];

        $recomputed = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        if (! hash_equals($recomputed, $result->certificateHash)) {
            throw new RuntimeException(
                "Integritas sertifikat TawarruqFx tidak valid! Terindikasi manipulasi kurs atau penetapan margin bunga terselubung."
            );
        }

        return true;
    }
}
