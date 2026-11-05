<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use RuntimeException;

final class SalamPositionBookCertificateVerifier
{
    /**
     * Memverifikasi integritas sertifikat kalkulasi SalamPositionBook.
     *
     * @throws RuntimeException Jika tanda tangan kriptografis tidak cocok atau data korup
     */
    public function verify(SalamPositionBookResult $result): true
    {
        $payload = [
            'commodityCode' => $result->commodityCode,
            'totalLongQuantity' => $result->totalLongQuantity,
            'totalShortQuantity' => $result->totalShortQuantity,
            'netOpenPositionQuantity' => $result->netOpenPositionQuantity,
            'totalLongCostCents' => $result->totalLongCostCents,
            'totalShortCommitmentCents' => $result->totalShortCommitmentCents,
            'markToMarketValueCents' => $result->markToMarketValueCents,
            'unrealizedPnlCents' => $result->unrealizedPnlCents,
            'fulfillmentRatioBps' => $result->fulfillmentRatioBps,
            'evaluatedAt' => $result->evaluatedAt,
        ];

        $recomputed = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        if (! hash_equals($recomputed, $result->certificateHash)) {
            throw new RuntimeException(
                'Integritas sertifikat SalamPositionBook tidak valid! Terindikasi manipulasi posisi buku komoditas salam.'
            );
        }

        return true;
    }
}
