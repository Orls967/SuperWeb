<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * M4 RoyaltyTierResolver
 *
 * Menyelesaikan tarif royalti minerba progresif (PP 19/2025) berdasarkan
 * komoditas, metode penambangan, pita kalori, dan tier harga acuan (HBA/HMA).
 */
final class RoyaltyTierResolver
{
    public function __construct(
        private readonly RoyaltyTierResolverCertificateVerifier $verifier = new RoyaltyTierResolverCertificateVerifier
    ) {}

    /**
     * Memecahkan tarif royalti dan menghitung kewajiban USD & IDR.
     */
    public function resolve(
        string $commodity,
        string $productType,
        string $miningMethod,
        int $calorieKcal,
        int $hbaOrHmaCents,
        int $volumeTons,
        int $benchmarkPriceCents,
        int $exchangeRateIdr
    ): RoyaltyTierResolverResult {
        if ($volumeTons <= 0 || $benchmarkPriceCents <= 0 || $exchangeRateIdr <= 0) {
            throw new InvalidArgumentException('Volume, harga patokan, dan kurs harus positif.');
        }

        $rateBps = 0;
        $tierLabel = '';

        if (strtolower($commodity) === 'coal' || strtolower($commodity) === 'batubara') {
            [$rateBps, $tierLabel] = $this->resolveCoalRate($miningMethod, $calorieKcal, $hbaOrHmaCents);
        } elseif (strtolower($commodity) === 'nickel' || strtolower($commodity) === 'nikel') {
            [$rateBps, $tierLabel] = $this->resolveNickelRate($productType, $hbaOrHmaCents);
        } else {
            throw new InvalidArgumentException("Komoditas {$commodity} belum didukung dalam penyelesaian royalti otomatis.");
        }

        $grossBasis = (int) ($volumeTons * $benchmarkPriceCents);
        $royaltyUsdCents = intdiv($grossBasis * $rateBps, 10000);
        $royaltyIdr = intdiv($royaltyUsdCents * $exchangeRateIdr, 100);

        return $this->assembleResult([
            'commodity' => $commodity,
            'product_type' => $productType,
            'mining_method' => $miningMethod,
            'calorie_kcal' => $calorieKcal,
            'hba_cents' => $hbaOrHmaCents,
            'volume_tons' => $volumeTons,
            'benchmark_cents' => $benchmarkPriceCents,
            'exchange_rate' => $exchangeRateIdr,
            'rate_bps' => $rateBps,
            'royalty_usd' => $royaltyUsdCents,
            'royalty_idr' => $royaltyIdr,
            'tier_label' => $tierLabel,
        ]);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function assembleResult(array $c): RoyaltyTierResolverResult
    {
        $payload = sprintf(
            '%s|%s|%s|%d|%d|%d|%d|%d|%d|%d|%d|%s',
            $c['commodity'],
            $c['product_type'],
            $c['mining_method'],
            $c['calorie_kcal'],
            $c['hba_cents'],
            $c['volume_tons'],
            $c['benchmark_cents'],
            $c['exchange_rate'],
            $c['rate_bps'],
            $c['royalty_usd'],
            $c['royalty_idr'],
            $c['tier_label']
        );

        $certificate = [
            'canonical_hash' => hash('sha256', $payload),
            'rate_rule_tier' => (string) $c['tier_label'],
            'verified_at' => now()->toIso8601String(),
        ];

        $result = new RoyaltyTierResolverResult(
            commodity: (string) $c['commodity'],
            productType: (string) $c['product_type'],
            miningMethod: (string) $c['mining_method'],
            calorieKcal: (int) $c['calorie_kcal'],
            hbaOrHmaCents: (int) $c['hba_cents'],
            volumeTons: (int) $c['volume_tons'],
            benchmarkPriceCents: (int) $c['benchmark_cents'],
            exchangeRateIdr: (int) $c['exchange_rate'],
            royaltyRateBps: (int) $c['rate_bps'],
            royaltyUsdCents: (int) $c['royalty_usd'],
            royaltyIdr: (int) $c['royalty_idr'],
            certificate: $certificate
        );

        $this->verifier->verify($result);

        return $result;
    }

    /**
     * Resolusi tarif royalti minerba terintegrasi database parameter berversi (PP 19/2025 Lampiran I).
     */
    public static function resolveRate(
        string $commodity,
        string $productType,
        string $miningMethod,
        int $calorificKcal,
        int $referencePriceUsdCents,
        mixed $rateVersions = null,
        int $volumeTons = 50000,
        int $benchmarkPriceCents = 7250,
        int $exchangeRateIdr = 16250
    ): RoyaltyTierResolverResult {
        $resolver = new self;

        // 1. Cek versi regulasi di database jika ada data versi yang cocok
        $matchedVersion = null;
        if ($rateVersions instanceof Collection || is_array($rateVersions)) {
            foreach ($rateVersions as $v) {
                if (strcasecmp($v->commodity, $commodity) === 0) {
                    $pMin = (int) ($v->price_tier_min_usd_cents ?? 0);
                    $pMax = (int) ($v->price_tier_max_usd_cents ?? 0);
                    if ($pMax > 0 && ($referencePriceUsdCents < $pMin || $referencePriceUsdCents > $pMax)) {
                        continue;
                    }
                    $cMin = (int) ($v->calorific_min_kcal ?? 0);
                    $cMax = (int) ($v->calorific_max_kcal ?? 0);
                    if ($cMax > 0 && ($calorificKcal < $cMin || $calorificKcal > $cMax)) {
                        continue;
                    }
                    $matchedVersion = $v;
                    break;
                }
            }
        }

        if ($matchedVersion) {
            $rateBps = (int) $matchedVersion->rate_bps;
            $tierLabel = (string) ($matchedVersion->source ?? 'PP 19/2025');

            $grossBasis = (int) ($volumeTons * $benchmarkPriceCents);
            $royaltyUsdCents = intdiv($grossBasis * $rateBps, 10000);
            $royaltyIdr = intdiv($royaltyUsdCents * $exchangeRateIdr, 100);

            return $resolver->assembleResult([
                'commodity' => $commodity,
                'product_type' => $productType,
                'mining_method' => $miningMethod,
                'calorie_kcal' => $calorificKcal,
                'hba_cents' => $referencePriceUsdCents,
                'volume_tons' => $volumeTons,
                'benchmark_cents' => $benchmarkPriceCents,
                'exchange_rate' => $exchangeRateIdr,
                'rate_bps' => $rateBps,
                'royalty_usd' => $royaltyUsdCents,
                'royalty_idr' => $royaltyIdr,
                'tier_label' => $tierLabel,
            ]);
        }

        return $resolver->resolve(
            commodity: $commodity,
            productType: $productType,
            miningMethod: $miningMethod,
            calorieKcal: $calorificKcal,
            hbaOrHmaCents: $referencePriceUsdCents,
            volumeTons: $volumeTons,
            benchmarkPriceCents: $benchmarkPriceCents,
            exchangeRateIdr: $exchangeRateIdr
        );
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function resolveCoalRate(string $method, int $kcal, int $hbaCents): array
    {
        $isUnderground = strtolower($method) === 'underground';

        if ($kcal <= 4200) {
            if ($hbaCents < 7000) {
                return [$isUnderground ? 400 : 500, 'COAL_LOW_TIER_1'];
            }
            if ($hbaCents < 9000) {
                return [$isUnderground ? 500 : 600, 'COAL_LOW_TIER_2'];
            }

            return [$isUnderground ? 700 : 900, 'COAL_LOW_TIER_3'];
        }

        if ($kcal <= 5200) {
            if ($hbaCents < 7000) {
                return [$isUnderground ? 600 : 700, 'COAL_MID_TIER_1'];
            }
            if ($hbaCents < 9000) {
                return [$isUnderground ? 750 : 850, 'COAL_MID_TIER_2'];
            }

            return [$isUnderground ? 950 : 1150, 'COAL_MID_TIER_3'];
        }

        // > 5200 kcal
        if ($hbaCents < 7000) {
            return [$isUnderground ? 850 : 950, 'COAL_HIGH_TIER_1'];
        }
        if ($hbaCents < 9000) {
            return [$isUnderground ? 1050 : 1150, 'COAL_HIGH_TIER_2'];
        }

        return [$isUnderground ? 1250 : 1350, 'COAL_HIGH_TIER_3'];
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function resolveNickelRate(string $product, int $hmaCents): array
    {
        // 18.000 USD = 1.800.000 cents
        if ($hmaCents < 1800000) {
            return [1400, 'NICKEL_ORE_TIER_1'];
        }
        if ($hmaCents < 2100000) {
            return [1500, 'NICKEL_ORE_TIER_2'];
        }
        if ($hmaCents < 2400000) {
            return [1600, 'NICKEL_ORE_TIER_3'];
        }
        if ($hmaCents < 3100000) {
            return [1800, 'NICKEL_ORE_TIER_4'];
        }

        return [1900, 'NICKEL_ORE_TIER_5'];
    }
}
