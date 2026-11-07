<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

use InvalidArgumentException;

final class RoyaltyTierResolverCertificateVerifier
{
    /**
     * Memvalidasi sertifikat matematis dan integritas perhitungan royalti minerba.
     *
     * @throws InvalidArgumentException jika sertifikat rusak atau tidak cocok
     */
    public function verify(RoyaltyTierResolverResult $result): bool
    {
        $cert = $result->certificate;

        if (! isset($cert['canonical_hash'], $cert['rate_rule_tier'], $cert['verified_at'])) {
            throw new InvalidArgumentException('Sertifikat royalti tidak lengkap.');
        }

        $expectedPayload = sprintf(
            '%s|%s|%s|%d|%d|%d|%d|%d|%d|%d|%d|%s',
            $result->commodity,
            $result->productType,
            $result->miningMethod,
            $result->calorieKcal,
            $result->hbaOrHmaCents,
            $result->volumeTons,
            $result->benchmarkPriceCents,
            $result->exchangeRateIdr,
            $result->royaltyRateBps,
            $result->royaltyUsdCents,
            $result->royaltyIdr,
            $cert['rate_rule_tier']
        );

        $expectedHash = hash('sha256', $expectedPayload);

        if (! hash_equals($expectedHash, (string) $cert['canonical_hash'])) {
            throw new InvalidArgumentException('Integritas sertifikat royalti rusak: hash tidak cocok.');
        }

        $grossBasis = (int) ($result->volumeTons * $result->benchmarkPriceCents);
        $expectedUsd = intdiv($grossBasis * $result->royaltyRateBps, 10000);
        if ($expectedUsd !== $result->royaltyUsdCents) {
            throw new InvalidArgumentException("Nilai royalti USD {$result->royaltyUsdCents} tidak sesuai rumus ({$expectedUsd}).");
        }

        $expectedIdr = intdiv($result->royaltyUsdCents * $result->exchangeRateIdr, 100);
        if ($expectedIdr !== $result->royaltyIdr) {
            throw new InvalidArgumentException("Nilai royalti IDR {$result->royaltyIdr} tidak sesuai konversi ({$expectedIdr}).");
        }

        return true;
    }
}
