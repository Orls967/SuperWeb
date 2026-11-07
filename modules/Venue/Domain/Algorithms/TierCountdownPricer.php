<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

/**
 * Algoritma V1: TierCountdownPricer
 *
 * Menghitung harga tiket dinamis dari tier countdown (Early Bird, GA, Door),
 * rasio permintaan terjual/kapasitas, dan faktor risiko cuaca pesisir.
 * Dibulatkan ke bawah ke Rp 1.000 dan dijepit di [floor, ceiling].
 */
final class TierCountdownPricer
{
    /**
     * @return array{price_idr: int, certificate: TierCountdownPriceCertificate}
     */
    public static function calculate(
        int $basePriceIdr,
        int $demandBps,
        int $rainProbabilityBps,
        int $floorPriceIdr,
        int $ceilingPriceIdr,
    ): array {
        // 1. Faktor Permintaan: < 5.000 bps -> 0; 5.000-7.999 -> +10%; >= 8.000 -> +20%
        $demandFactorBps = 0;
        if ($demandBps >= 8000) {
            $demandFactorBps = 2000;
        } elseif ($demandBps >= 5000) {
            $demandFactorBps = 1000;
        }

        // 2. Faktor Cuaca: peluang hujan >= 6.000 bps -> -10%
        $weatherFactorBps = 0;
        if ($rainProbabilityBps >= 6000) {
            $weatherFactorBps = -1000;
        }

        // Hitung perkalian rasional beruntun
        // P_demand = base * (10000 + demandFactorBps) / 10000
        $priceAfterDemand = intdiv($basePriceIdr * (10000 + $demandFactorBps), 10000);

        // P_weather = P_demand * (10000 + weatherFactorBps) / 10000
        $priceAfterWeather = intdiv($priceAfterDemand * (10000 + $weatherFactorBps), 10000);

        // Dibulatkan ke bawah ke Rp 1.000
        $rounded = intdiv($priceAfterWeather, 1000) * 1000;

        // Dijepit di [floor, ceiling]
        $final = min($ceilingPriceIdr, max($floorPriceIdr, $rounded));

        $signature = TierCountdownPriceCertificateVerifier::sign(
            $basePriceIdr,
            $demandBps,
            $rainProbabilityBps,
            $floorPriceIdr,
            $ceilingPriceIdr,
            $rounded,
            $final
        );

        $certificate = new TierCountdownPriceCertificate(
            basePriceIdr: $basePriceIdr,
            demandBps: $demandBps,
            rainProbabilityBps: $rainProbabilityBps,
            floorPriceIdr: $floorPriceIdr,
            ceilingPriceIdr: $ceilingPriceIdr,
            calculatedPriceIdr: $rounded,
            finalPriceIdr: $final,
            hmacSignature: $signature
        );

        TierCountdownPriceCertificateVerifier::verify($certificate);

        return [
            'price_idr' => $final,
            'certificate' => $certificate,
        ];
    }
}
