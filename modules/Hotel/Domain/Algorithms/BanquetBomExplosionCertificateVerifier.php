<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final class BanquetBomExplosionCertificateVerifier
{
    public static function verify(BanquetBomExplosionCertificate $certificate, string $secretKey = 'superweb-hotel-mice-salt'): bool
    {
        $payload = implode('|', [
            $certificate->beoId,
            $certificate->guaranteedPax,
            $certificate->preparedPax,
            $certificate->totalFoodCogsIdr,
            $certificate->totalBeverageCogsIdr,
            $certificate->totalLaborCostIdr,
            $certificate->totalDirectCostIdr,
            $certificate->waitersRequired,
            $certificate->cooksRequired,
            json_encode($certificate->ingredientsSummary, JSON_UNESCAPED_SLASHES),
            json_encode($certificate->beveragesSummary, JSON_UNESCAPED_SLASHES),
        ]);

        $expectedHash = hash_hmac('sha256', $payload, $secretKey);

        return hash_equals($expectedHash, $certificate->signatureHash);
    }
}
