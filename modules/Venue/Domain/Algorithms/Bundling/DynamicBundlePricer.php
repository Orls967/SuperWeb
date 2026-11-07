<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Bundling;

class DynamicBundlePricer
{
    /**
     * Menghitung harga paket bundling dinamis (Event + Hotel + Transport)
     * dengan menerapkan lonjakan okupansi (surge) dan diskon lead time,
     * serta mengunci batas dasar Floor Margin Guardrail (FinalPrice >= TotalFloorCost).
     */
    public function calculatePrice(DynamicBundleInput $input): DynamicBundleResult
    {
        $totalFloorCostIdr = 0;
        $totalBasePriceIdr = 0;

        foreach ($input->components as $component) {
            $totalFloorCostIdr += (int) ($component['floor_cost'] ?? 0);
            $totalBasePriceIdr += (int) ($component['base_price'] ?? 0);
        }

        // Hitung surge okupansi rata-rata
        $combinedOccupancy = intdiv($input->venueOccupancyPercentage + $input->hotelOccupancyPercentage, 2);
        $surgeBps = 0;
        if ($combinedOccupancy > 70) {
            // Tiap 1% di atas 70% menambah 70 bps (0.7%)
            $surgeBps = ($combinedOccupancy - 70) * 70;
        }

        // Hitung diskon lead time (semakin jauh hari, semakin besar insentif early-bird)
        $leadTimeDiscountBps = 0;
        if ($input->leadTimeDays >= 30) {
            $leadTimeDiscountBps = 1500; // 15%
        } elseif ($input->leadTimeDays >= 14) {
            $leadTimeDiscountBps = 1000; // 10%
        } elseif ($input->leadTimeDays >= 7) {
            $leadTimeDiscountBps = 500; // 5%
        } else {
            $leadTimeDiscountBps = 0; // Last-minute booking
        }

        $surgeAmount = intdiv($totalBasePriceIdr * $surgeBps, 10000);
        $discountAmount = intdiv($totalBasePriceIdr * $leadTimeDiscountBps, 10000);
        $rawPrice = $totalBasePriceIdr + $surgeAmount - $discountAmount;

        // Enforce Floor Margin Guardrail: harga jual dilarang di bawah total biaya dasar (floor cost)
        $finalBundlePriceIdr = max($rawPrice, $totalFloorCostIdr);

        $signature = DynamicBundlePriceVerifier::sign(
            $totalFloorCostIdr,
            $totalBasePriceIdr,
            $surgeBps,
            $leadTimeDiscountBps,
            $finalBundlePriceIdr
        );

        $cert = new DynamicBundlePriceCertificate(
            totalFloorCostIdr: $totalFloorCostIdr,
            totalBasePriceIdr: $totalBasePriceIdr,
            surgeBps: $surgeBps,
            leadTimeDiscountBps: $leadTimeDiscountBps,
            finalBundlePriceIdr: $finalBundlePriceIdr,
            hmacSignature: $signature
        );

        return new DynamicBundleResult(
            totalFloorCostIdr: $totalFloorCostIdr,
            totalBasePriceIdr: $totalBasePriceIdr,
            surgeBps: $surgeBps,
            leadTimeDiscountBps: $leadTimeDiscountBps,
            finalBundlePriceIdr: $finalBundlePriceIdr,
            certificate: $cert
        );
    }
}
