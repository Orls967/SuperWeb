<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

/**
 * H12: PartnerPointConverter
 * Konversi bilateral poin aliansi dengan kalkulasi fee dan netting settlement.
 */
final class PartnerPointConverter
{
    public function convert(
        string $partnerCode,
        int $pointsToConvert,
        float $exchangeRatio,
        float $transferFeePercent,
        int $pointValuationIdr = 100
    ): PartnerPointConversionCertificate {
        if ($pointsToConvert <= 0) {
            throw new InvalidArgumentException('Poin yang dikonversi harus positif.');
        }

        if ($exchangeRatio <= 0.0) {
            throw new InvalidArgumentException('Exchange ratio harus positif.');
        }

        if ($transferFeePercent < 0.0 || $transferFeePercent >= 1.0) {
            throw new InvalidArgumentException('Transfer fee percent harus di antara 0.0 dan 1.0.');
        }

        // Units of partner currency = floor(points / ratio)
        $partnerUnitsCredited = (int) floor($pointsToConvert / $exchangeRatio);

        // Monetary value of points in IDR
        $totalLiabilityDebitedIdr = $pointsToConvert * $pointValuationIdr;

        // Platform fee
        $transferFeeIdr = (int) ceil($totalLiabilityDebitedIdr * $transferFeePercent);

        // Net payable to partner
        $netPayableToPartnerIdr = $totalLiabilityDebitedIdr - $transferFeeIdr;

        $payload = json_encode([
            'partner_code' => $partnerCode,
            'points_converted' => $pointsToConvert,
            'partner_units' => $partnerUnitsCredited,
            'liability_debited' => $totalLiabilityDebitedIdr,
            'transfer_fee' => $transferFeeIdr,
            'net_payable' => $netPayableToPartnerIdr,
        ], JSON_THROW_ON_ERROR);

        $signatureHash = hash('sha256', $payload);

        return new PartnerPointConversionCertificate(
            partnerCode: $partnerCode,
            pointsConverted: $pointsToConvert,
            partnerUnitsCredited: $partnerUnitsCredited,
            totalLiabilityDebitedIdr: $totalLiabilityDebitedIdr,
            transferFeeIdr: $transferFeeIdr,
            netPayableToPartnerIdr: $netPayableToPartnerIdr,
            signatureHash: $signatureHash
        );
    }
}
