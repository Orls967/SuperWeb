<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

use InvalidArgumentException;

final class CoalPriceAdjusterCertificateVerifier
{
    /**
     * Memvalidasi perhitungan penyesuaian harga batubara komersial dan integritas sertifikat.
     *
     * @throws InvalidArgumentException jika hasil atau sertifikat tidak valid
     */
    public function verify(CoalPriceAdjusterResult $result): bool
    {
        $cert = $result->certificate;

        if (! isset($cert['canonical_hash'], $cert['pricing_rule_verified'], $cert['verified_at'])) {
            throw new InvalidArgumentException('Sertifikat penyesuaian harga batubara tidak lengkap.');
        }

        $payload = sprintf(
            '%d|%d|%d|%d|%d|%d|%d|%d|%d|%d|%d|%d|%d|%s|%s',
            $result->basePriceCents,
            $result->actualGarKcal,
            $result->referenceGarKcal,
            $result->actualAshPermil,
            $result->baseAshLimitPermil,
            $result->actualSulfurPermil,
            $result->baseSulfurLimitPermil,
            $result->volumeTons,
            $result->garAdjustedPriceCents,
            $result->ashPenaltyCents,
            $result->sulfurPenaltyCents,
            $result->finalPriceCentsPerTon,
            $result->totalInvoiceUsdCents,
            $result->isRejected ? '1' : '0',
            $result->rejectionReason ?? ''
        );

        $expectedHash = hash('sha256', $payload);
        if (! hash_equals($expectedHash, (string) $cert['canonical_hash'])) {
            throw new InvalidArgumentException('Integritas sertifikat harga batubara rusak: hash tidak cocok.');
        }

        if ($result->isRejected) {
            if ($result->finalPriceCentsPerTon !== 0 || $result->totalInvoiceUsdCents !== 0) {
                throw new InvalidArgumentException('Pengapalan ditolak namun harga akhir bukan nol.');
            }

            return true;
        }

        $expectedGarPrice = intdiv($result->basePriceCents * $result->actualGarKcal, $result->referenceGarKcal);
        if ($expectedGarPrice !== $result->garAdjustedPriceCents) {
            throw new InvalidArgumentException("Penyesuaian GAR tidak cocok: {$result->garAdjustedPriceCents} != {$expectedGarPrice}");
        }

        $expectedFinalPrice = $result->garAdjustedPriceCents - $result->ashPenaltyCents - $result->sulfurPenaltyCents;
        if ($expectedFinalPrice !== $result->finalPriceCentsPerTon) {
            throw new InvalidArgumentException("Harga final per ton tidak cocok: {$result->finalPriceCentsPerTon} != {$expectedFinalPrice}");
        }

        $expectedTotal = (int) ($result->finalPriceCentsPerTon * $result->volumeTons);
        if ($expectedTotal !== $result->totalInvoiceUsdCents) {
            throw new InvalidArgumentException("Total invoice USD tidak cocok: {$result->totalInvoiceUsdCents} != {$expectedTotal}");
        }

        return true;
    }
}
