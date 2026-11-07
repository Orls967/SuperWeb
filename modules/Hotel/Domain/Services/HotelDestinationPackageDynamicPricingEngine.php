<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\DestinationPackage;

class HotelDestinationPackageDynamicPricingEngine
{
    /**
     * Menghitung penyesuaian tarif dinamis paket destinasi berdasarkan faktor musim dan sisa ketersediaan kuota.
     *
     * @return array{package_code: string, base_price_idr: int, dynamic_price_idr: int, multiplier_applied: float, season_factor: string}
     */
    public function computeDynamicPackagePrice(
        string $destinationPackageId,
        string $travelDate,
        int $remainingQuota = 5
    ): array {
        $package = DestinationPackage::findOrFail($destinationPackageId);
        $basePrice = (int) $package->total_price_idr;

        $month = (int) substr($travelDate, 5, 2);
        $multiplier = 1.0;
        $season = 'REGULAR';

        // Juli-Agustus (Peak Summer) & Desember (Year-End)
        if (in_array($month, [7, 8, 12], true)) {
            $multiplier = 1.25;
            $season = 'HIGH_PEAK';
        } elseif (in_array($month, [4, 5, 6, 9], true)) {
            $multiplier = 1.10;
            $season = 'SHOULDER';
        }

        // Surge ketersediaan mepet (< 3 kuota tersisa)
        if ($remainingQuota <= 2) {
            $multiplier += 0.10;
        }

        $dynamicPrice = (int) round($basePrice * $multiplier);

        return [
            'package_code' => $package->package_code,
            'base_price_idr' => $basePrice,
            'dynamic_price_idr' => $dynamicPrice,
            'multiplier_applied' => round($multiplier, 2),
            'season_factor' => $season,
        ];
    }
}
