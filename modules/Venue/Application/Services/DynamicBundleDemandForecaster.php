<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use InvalidArgumentException;
use Modules\Venue\Domain\Models\DynamicBundlePackage;

/**
 * Layanan Prediksi Permintaan Dinamis & Optimasi Margin Paket Bundling (Tiket + Hotel + Shuttle).
 * Menghitung elastisitas harga silang (cross-price elasticity), kurva laju penyerapan inventaris,
 * dan merekomendasikan penyesuaian diskon bundling berbasis kapasitas riil kamar hotel dan armada shuttle.
 */
final class DynamicBundleDemandForecaster
{
    /**
     * Hitung elastisitas permintaan harga untuk komponen paket berdasarkan data historis penjualan.
     * Mengembalikan koefisien elastisitas permintaan dalam basis poin (misal 15000 bps = 1.50).
     *
     * @param  int  $basePriceIdr  Harga referensi awal
     * @param  int  $newPriceIdr  Harga penawaran aktual
     * @param  int  $baseDemandQuantity  Jumlah permintaan awal
     * @param  int  $newDemandQuantity  Jumlah permintaan setelah perubahan harga
     */
    public function calculatePriceElasticityBps(
        int $basePriceIdr,
        int $newPriceIdr,
        int $baseDemandQuantity,
        int $newDemandQuantity
    ): int {
        if ($basePriceIdr <= 0 || $newPriceIdr <= 0) {
            throw new InvalidArgumentException('Harga harus bernilai positif integer.');
        }
        if ($baseDemandQuantity <= 0) {
            return 10000; // Elastisitas uniter netral (1.0)
        }

        $deltaQ = $newDemandQuantity - $baseDemandQuantity;
        $avgQ = (int) intdiv($baseDemandQuantity + $newDemandQuantity, 2);
        if ($avgQ <= 0) {
            $avgQ = 1;
        }

        $deltaP = $newPriceIdr - $basePriceIdr;
        $avgP = (int) intdiv($basePriceIdr + $newPriceIdr, 2);
        if ($avgP <= 0 || $deltaP === 0) {
            return 10000;
        }

        // Elastisitas Titik Tengah (Midpoint formula): (% delta Q) / (% delta P)
        $pctDeltaQ = ($deltaQ * 10000) / $avgQ;
        $pctDeltaP = ($deltaP * 10000) / $avgP;

        $elasticity = abs((int) (($pctDeltaQ / $pctDeltaP) * 10000));

        return (int) min(50000, max(0, $elasticity));
    }

    /**
     * Proyeksi Kurva Penyerapan Inventaris Paket (Depletion Rate Forecasting).
     * Memodelkan berapa hari tersisa hingga paket bundling terjual habis dengan laju penyerapan saat ini.
     *
     * @param  int  $totalAllocated  Total unit paket yang dialokasikan
     * @param  int  $totalSold  Total unit yang telah laku
     * @param  int  $daysOnSale  Jumlah hari penjualan aktif
     * @param  int  $daysUntilEvent  Sisa hari menuju tanggal pelaksanaan festival/konser
     * @return array{
     *   remaining_inventory: int,
     *   daily_velocity_units: float,
     *   projected_days_to_sellout: int,
     *   sellout_risk: string,
     *   recommended_discount_adjustment_bps: int
     * }
     */
    public function forecastInventoryDepletion(
        int $totalAllocated,
        int $totalSold,
        int $daysOnSale,
        int $daysUntilEvent
    ): array {
        $remaining = max(0, $totalAllocated - $totalSold);
        $daysOnSaleSafe = max(1, $daysOnSale);
        $dailyVelocity = round($totalSold / $daysOnSaleSafe, 2);

        $daysToSellout = $dailyVelocity > 0
            ? (int) ceil($remaining / $dailyVelocity)
            : 999;

        // Tentukan status risiko ketersediaan
        $risk = match (true) {
            $remaining === 0 => 'SOLD_OUT',
            $daysToSellout <= ($daysUntilEvent / 2) => 'HIGH_VELOCITY_PREMIUM_OPPORTUNITY',
            $daysToSellout > $daysUntilEvent => 'SLOW_VELOCITY_OVERSTOCK_RISK',
            default => 'BALANCED_PACING',
        };

        // Rekomendasi penyesuaian diskon:
        // Jika lambat (overstock risk), perbesar diskon bundling hingga +500 bps (+5%).
        // Jika sangat cepat (high velocity), kurangi diskon hingga -500 bps (-5%) untuk maksimalkan margin.
        $discountAdjustmentBps = match ($risk) {
            'SLOW_VELOCITY_OVERSTOCK_RISK' => 500,
            'HIGH_VELOCITY_PREMIUM_OPPORTUNITY' => -500,
            default => 0,
        };

        return [
            'remaining_inventory' => $remaining,
            'daily_velocity_units' => $dailyVelocity,
            'projected_days_to_sellout' => $daysToSellout,
            'sellout_risk' => $risk,
            'recommended_discount_adjustment_bps' => $discountAdjustmentBps,
        ];
    }

    /**
     * Analisis Matriks Silang Komponen Bundling (Tiket vs Hotel vs Shuttle).
     * Memberikan rekomendasi alokasi kuota kamar hotel rekanan dan jadwal keberangkatan shuttle.
     *
     * @param  int  $hotelOccupancyRateBps  Tingkat hunian hotel rekanan dalam basis poin (0 - 10000)
     * @param  int  $shuttleSeatOccupancyBps  Tingkat keterisian kursi armada shuttle (0 - 10000)
     * @param  int  $ticketDemandIndex  Indeks permintaan tiket pertunjukan (100 = normal, >150 = lonjakan)
     * @return array{
     *   package_viability_score: int,
     *   recommended_package_tier: string,
     *   bundle_subsidy_allocation: array<string, int>,
     *   cross_sell_opportunity: bool
     * }
     */
    public function evaluateCrossSubsidization(
        int $hotelOccupancyRateBps,
        int $shuttleSeatOccupancyBps,
        int $ticketDemandIndex
    ): array {
        // Semakin rendah keterisian hotel, semakin besar insentif subsidi silang ke paket tiket
        $hotelVacancyScore = max(0, 10000 - $hotelOccupancyRateBps);
        $shuttleVacancyScore = max(0, 10000 - $shuttleSeatOccupancyBps);

        // Viability score (0 - 100)
        $viabilityScore = (int) intdiv(
            ($ticketDemandIndex * 40) +
            (intdiv($hotelVacancyScore, 100) * 35) +
            (intdiv($shuttleVacancyScore, 100) * 25),
            100
        );
        $viabilityScore = max(0, min(100, $viabilityScore));

        $tier = match (true) {
            $viabilityScore >= 80 => 'PLATINUM_VIP_EXPERIENCE',
            $viabilityScore >= 60 => 'GOLD_DELUXE_WEEKENDER',
            $viabilityScore >= 40 => 'SILVER_STANDARD_BUNDLE',
            default => 'BRONZE_ESSENTIAL_ACCESS',
        };

        // Alokasi pembagian porsi subsidi silang (dalam basis poin dari total nilai diskon)
        $subsidyAllocation = [
            'hotel_partner_bps' => 5000,   // Hotel menanggung 50% potongan harga via tarif grosir
            'event_promoter_bps' => 3500, // Promotor menanggung 35%
            'shuttle_operator_bps' => 1500, // Operator shuttle menanggung 15%
        ];

        return [
            'package_viability_score' => $viabilityScore,
            'recommended_package_tier' => $tier,
            'bundle_subsidy_allocation' => $subsidyAllocation,
            'cross_sell_opportunity' => $ticketDemandIndex >= 120 && $hotelOccupancyRateBps <= 7000,
        ];
    }

    /**
     * Audit kelayakan ekonomi paket sebelum dibuka ke publik.
     */
    public function isPackageEconomicallySound(DynamicBundlePackage $package): bool
    {
        $faceValue = $package->ticket_face_value_idr;
        $hotelPrice = $package->hotel_room_rate_idr;
        $shuttlePrice = $package->shuttle_fare_idr;

        $unbundledTotal = $faceValue + $hotelPrice + $shuttlePrice;
        $actualPackagePrice = $package->bundle_price_idr;

        // Diskon tidak boleh melebihi 35% dan harga paket tidak boleh di bawah harga pokok komponen hotel & shuttle
        $minAllowedPrice = (int) intdiv($unbundledTotal * 65, 100);
        $floorCost = $hotelPrice + $shuttlePrice;

        return $actualPackagePrice >= $minAllowedPrice && $actualPackagePrice >= $floorCost;
    }
}
